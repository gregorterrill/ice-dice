<?php
namespace modules\supersearch;
use modules\supersearch\variables\SuperSearchVariable;

use Craft;
use yii\base\Module as BaseModule;
use yii\base\Event;
use craft\web\twig\variables\CraftVariable;
use craft\services\Search;
use craft\services\Elements;
use craft\base\Element;
use craft\base\Field;
use craft\elements\Entry;
use craft\events\RegisterElementSearchableAttributesEvent;
use craft\events\IndexKeywordsEvent;
use craft\events\SearchEvent;
use craft\events\ModelEvent;
use craft\events\DefineAttributeKeywordsEvent;
use craft\events\DefineFieldKeywordsEvent;
use craft\events\ElementEvent;
use craft\helpers\ElementHelper;
use craft\helpers\Search as SearchHelper;
use craft\helpers\StringHelper;
use craft\search\SearchQuery;
use craft\search\SearchQueryTerm;
use craft\search\SearchQueryTermGroup;
use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;
use yii\db\Schema;

use Meilisearch\Client as MeilisearchClient;
use Algolia\AlgoliaSearch\SearchClient as AlgoliaClient;


/**
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('supersearch')`.
 */
class SuperSearchModule extends BaseModule
{

  // ----------------------------------------------------------------------------------------------[ INIT ]
  public function init(): void
  {
    // Set a @modules alias pointed to the modules/ directory
    Craft::setAlias('@modules', __DIR__);

    // Set the controllerNamespace based on whether this is a console or web request
    if (Craft::$app->request->isConsoleRequest) {
        $this->controllerNamespace = 'modules\\supersearch\\console\\controllers';
    } else {
        $this->controllerNamespace = 'modules\\supersearch\\controllers';
    }

    parent::init();

    // Defer most setup tasks until Craft is fully initialized
    Craft::$app->onInit(function() {
        $this->attachEventHandlers();
    });
  }

  // ----------------------------------------------------------------------------------------------[ EVENT HANDLERS ]
  private function attachEventHandlers(): void
  {
      $this->_registerSuperSearchVariable();

      // Indexing Events
      //$this->_registerBeforeUpdateSearchIndex();
      //$this->_registerRegisterSearchableAttributes();
      //$this->_registerDefineAttributeKeywords();
      //$this->_registerBeforeIndexKeywords();
      //$this->_registerDefineFieldKeywords();
  
      // Search Events
      //$this->_registerBeforeScoreResults();
      //$this->_registerAfterSearch();

      // External Search
      //$this->_registerIndexWithExternalServicesOnSave();
  }

  // ----------------------------------------------------------------------------------------------[ VARIABLE ]
  /**
   * Register custom variable to use in templates
   */
  private function _registerSuperSearchVariable(): void
  {
    Event::on(
      CraftVariable::class,
      CraftVariable::EVENT_INIT,
      function (Event $event) {
        $variable = $event->sender;
        $variable->set('supersearch', SuperSearchVariable::class);
      }
    );
  }

  // ----------------------------------------------------------------------------------------------[ INDEXING RELATED EVENTS ]

  /**
   * EVENT_BEFORE_UPDATE_SEARCH_INDEX can be used to prevent indexing
   */
  private function _registerBeforeUpdateSearchIndex(): void
  {
    Event::on(
      Elements::class,
      Elements::EVENT_BEFORE_UPDATE_SEARCH_INDEX,
      function (ElementEvent $event) {

        $element = $event->element;

        // Prevent entries with "Prevent Indexing" lightswitch on from ever being added to the searchindex 
        // (note that this does NOT delete existing rows, even when resaving section via CLI)
        if ($element instanceof Entry && $element->preventIndexing == true) {
          $event->isValid = false;
        }

        // Prevent new "Staff" entries from ever being added to the searchindex 
        // if ($element instanceof Entry && $element->sectionId == 2) {
        //   $event->isValid = false;
        // }
      }
    );
  }

  /**
   * EVENT_REGISTER_SEARCHABLE_ATTRIBUTES can be used to set up custom attributes
   */
  private function _registerRegisterSearchableAttributes(): void
  {
    Event::on(
      Entry::class,
      Entry::EVENT_REGISTER_SEARCHABLE_ATTRIBUTES,
      function (RegisterElementSearchableAttributesEvent $event) {
        // Note that $event->sender is always null here, so we can't do any checking for entry type, section, etc.
        // Add a "Menu Category" attribute
        $event->attributes[] = 'menucategory';
      }
    );
  }

  /**
   * EVENT_DEFINE_KEYWORDS on Elements can be used to handle keywords for the above searchable attributes
   */
  private function _registerDefineAttributeKeywords(): void
  {
    Event::on(
      Element::class,
      Element::EVENT_DEFINE_KEYWORDS,
      function (DefineAttributeKeywordsEvent $event) {

        $element = $event->sender;

        if ($event->attribute == 'menucategory') {
          // We have to set handled to true, or Craft will try to access a "menucategory" value on the element that doesn't exist and throw an error
          $event->handled = true;

          // If this is a menuItem (7) in the menu section (6), handle "Menu Category" by getting the parent menu category and adding the word menu, plus the parent title
          if ($element instanceof Entry && $element->typeId == 7 && $element->sectionId == 6) {
            $event->keywords = 'menu ' . $element->parent->title;
          } else {
            // If it's not a menu item, it'll just end up with a blank row, but there's no way to prevent that here
            // We can prevent the blank row from actually being saved in EVENT_BEFORE_INDEX_KEYWORDS
          }
        }
      }
    );
  }

  /**
   * EVENT_BEFORE_INDEX_KEYWORDS can be used to modify keywords before they're saved
   */
  private function _registerBeforeIndexKeywords(): void
  {
    Event::on(
      Search::class,
      Search::EVENT_BEFORE_INDEX_KEYWORDS,
      function (IndexKeywordsEvent $event) {
        
        // This is what we've got to work with
        $element = $event->element;
        $attribute = $event->attribute;
        $fieldId = $event->fieldId;
        $keywords = $event->keywords;

        // Prevent the blank "Menu Category" rows from being saved (on non-menu item entries, eg.)
        if ($attribute == 'menucategory' && !$keywords)  {
          $event->isValid = false;
        }

        // //If "hidden movement" appears in the "Game Tags" field - no it doesn't
        // if ($fieldId == 12 && StringHelper::contains($keywords, 'hidden movement', false)) {
        //   $event->keywords = StringHelper::replaceAll($event->keywords, ['hidden movement'], [''], false);
        //   // If we wanted to prevent Game Tags fields containing "hidden movement" from ever being indexed, 
        //   // we could set this to false to prevent the row from saving
        //   $event->isValid = true;
        // }
      }
    );
  }

  /**
   * EVENT_DEFINE_KEYWORDS on Fields can be used to handle custom field keywords
   */
  private function _registerDefineFieldKeywords(): void
  {
    Event::on(
      Field::class,
      Field::EVENT_DEFINE_KEYWORDS,
      function (DefineFieldKeywordsEvent $event) {
        $field = $event->sender;

        // If there are no tags set for the Game Tags field, set the keywords to 'uncategorized'
        // We're checking $event->value->all() because $event->value for this field type returns a TagQuery
        if ($field->handle == 'gameTags' && !count($event->value->all())) {
          $event->keywords = 'uncategorized';
          $event->handled = true; 
        }
      
      }
    );
  }

  // ----------------------------------------------------------------------------------------------[ SEARCH QUERY RELATED EVENTS ]

  /**
   * EVENT_BEFORE_SCORE_RESULTS is where you would manually calculate 
   * your own scores from scratch so Craft doesn't bother
   */
  private function _registerBeforeScoreResults(): void
  {
    Event::on(
      Search::class,
      Search::EVENT_BEFORE_SCORE_RESULTS,
      function (SearchEvent $event) {

        // If results are set, Craft will score these new results instead (if we weren't providing scores)
        $event->results = array_filter($event->results, function($item) {
          return in_array($item['elementId'], [786, 794]); // only allow results containing the word "Monica"
        });
        return;

        // We're going to use this function to do all the scoring ourselves, using much simpler logic
        // than Craft uses by default. We're just going to use the EXACT query text and the score will
        // be the number of times that text appears in any field/attribute for the element.

        // This is EXACTLY what was typed in - we don't care about AND terms and OR groups, make it simple!
        $searchQuery = trim($event->query->getQuery());
        
        // We need the site ID to determine the language
        $siteId = $event->siteId;
        if ($siteId && !is_array($siteId)) {
          $site = Craft::$app->getSites()->getSiteById($siteId);
        }
      
        // We're going to set new results and calculate scores so that the Search service will skip doing that
        $scores = [];

        // Loop through results and calculate the score for each row
        foreach ($event->results as $i => $row) {
          $score = 0;
          $elementId = $row['elementId'];

          // Normalize the search query, and count how many times it appears in the row
          $keywords = SearchHelper::normalizeKeywords($searchQuery, [], true, $site->language ?? null);
          $score = StringHelper::countSubstrings($row['keywords'], $keywords);

          // Add this to our new results array, and create or update the key/value in the scores array
          if (!isset($scores[$elementId])) {
            $scores[$elementId] = $score;
          } else {
            $scores[$elementId] += $score;
          }
        }

        // If scores are set, Craft's scoring will be skipped - it just uses our scores
        $event->scores = $scores;
      }
    );
  }

  /**
   * EVENT_AFTER_SEARCH is where you would modify scores already calculated by Craft
   */
  private function _registerAfterSearch(): void
  {
    Event::on(
      Search::class,
      Search::EVENT_AFTER_SEARCH,
      function (SearchEvent $event) {        
        $elementQuery = $event->elementQuery; // Original ElementQuery instance
        $searchQuery = $event->query; // SearchQuery instance, with parsed tokens
        $userSearchTerms = $searchQuery->getQuery(); // What the user actually searched for
        $siteId = $event->siteId; // Site(s) the search was performed in
        $results = $event->results; // Raw index matches (there may be multiple rows per element)
        $scores = $event->scores; // Corresponding element score totals, indexed by element ID - THIS IS WHAT WE CAN MODIFY HERE

        // Show Incan Gold (ID: 1204) if someone searches for Diamant
        if (StringHelper::contains($userSearchTerms, 'diamant', false)) {
          $event->scores[1204] = 100;
        }

        // // Go through all Craft-determined scores
        // foreach($scores as $elementId => $score) {
          
        //   // Any result containing the keyword Everdell will have its score doubled
        //   $resultsMatchingThisScoreElement = array_filter($results, function($result) use ($elementId) {
        //     return $result['elementId'] == $elementId;
        //   });
        //   foreach ($resultsMatchingThisScoreElement as $result) {
        //     if (StringHelper::contains($result['keywords'], 'everdell', false)) {
        //       // Make sure score at least 1 (because 0*2=0)
        //       $event->scores[$elementId] = max([1, $scores[$elementId] * 2]);
        //     }
        //   }

        //   // Remove any results with a score of 0
        //   if ($scores[$elementId] < 1) {
        //     unset($event->scores[$elementId]);
        //     continue;
        //   }
        // }
      
      }
    );
  }


  // ----------------------------------------------------------------------------------------------[ ALGOLIA AND MEILISEARCH INTEGRATION ]
  // Save entries to Meilisearch and Algolia on save
  // Run Meilisearch with:  ~/Projects/meilisearch/meilisearch --master-key 58zmbORuI01yOiho0qNGO1xj5lK3D9KX76npjWEu2wQ
  // Clear local and external indices completely with our custom CLI command:  php craft supersearch/index/wipe
  // Reindex with the built-in CLI command:  php craft resave/entries --updateSearchIndex
  private function _registerIndexWithExternalServicesOnSave(): void
  {
    Event::on(
      Entry::class,
      Entry::EVENT_AFTER_SAVE,
      function (ModelEvent $event) {       
        $entry = $event->sender;   

        if (ElementHelper::isDraft($entry) || ElementHelper::isRevision($entry)) return;

        $meilisearchClient = new MeilisearchClient(getenv('MEILISEARCH_DOMAIN'), getenv('MEILISEARCH_KEY'));
        $meilisearchIndex = $meilisearchClient->index(getenv('EXTERNAL_SEARCH_INDEX'));

        $algoliaClient = AlgoliaClient::create(getenv('ALGOLIA_APPLICATION_ID'), getenv('ALGOLIA_ADMIN_API_KEY'));
        $algoliaIndex = $algoliaClient->initIndex(getenv('EXTERNAL_SEARCH_INDEX'));

        $entryData = $this->_transformEntryData($entry);

        if ('live' == $entry->status) {
          $algoliaIndex->saveObject($entryData, ['objectIDKey' => 'id']);
          $meilisearchIndex->addDocuments([$entryData]);
        } else {
          $algoliaIndex->deleteObject($entryData['id']);
          $meilisearchClient->deleteDocument((int)$entryData['id']);
        }

      }
    );
  }

  private function _transformEntryData($entry): array
  {
    // We're using a variable in a hacky way only because the logic is already there, but it should be extracted to a service
    $variable = new SuperSearchVariable;

    $entryData = [
      'id' => $entry->id,
      'section' => $entry->section->handle,
      'name' => $entry->title,
      'slug' => $entry->slug,
      'url' => $variable->getResultLink($entry),
      'postDate' => $entry->postDate->format('Y-m-d')
    ];

    if ($entry->extraSearchKeywords) {
      $entryData['keywords'] = $entry->extraSearchKeywords;
    }

    if ($entry->section->handle == 'games') {
      $entryData['boxCover'] = $entry->coverImage->one()->url;
      $entryData['minPlayers'] = $entry->minPlayers;
      $entryData['maxPlayers'] = $entry->maxPlayers;
      $entryData['minLength'] = $entry->minLength;
      $entryData['maxLength'] = $entry->maxLength;
      $entryData['tags'] = $entry->gameTags->collect()->map(function($item) {
        return $item->title;
      })->join(' ');

    } elseif ($entry->section->handle == 'staff') {
      $entryData['position'] = $entry->position;
      $entryData['content'] = strip_tags($entry->description);
      $entryData['favoriteGames'] = $entry->favoriteGames->collect()->map(function($item) {
        return $item->title;
      })->join(' ');

    } elseif ($entry->section->handle == 'menu') {
      $entryData['content'] = strip_tags($entry->description);
      $entryData['price'] = $entry->price;

      if ($entry->type->handle == 'item') {
        $entryData['category'] = $entry->parent->title;
      }

    } elseif ($entry->section->handle == 'events') {

      $entryData['content'] = strip_tags($entry->description);
      if ($entry->type->handle == 'weeklyEvent') {
        $entryData['dayOfWeek'] = $entry->dayOfWeek->label;
        $entryData['startTime'] = $entry->startTime;
      } else {
        $entryData['startDate'] = $entry->startDate;
      }
      $entryData['duration'] = $entry->duration;

    } elseif ($entry->section->handle == 'blog' || $entry->section->handle == 'pages') {
      $entryData['content'] = implode(' ', array_map(function($matrixBlock) {
        return strip_tags($matrixBlock->textContent);
      }, $entry->pageContent->type('textContent')->all()));
    
    }

    return $entryData;
  }

}