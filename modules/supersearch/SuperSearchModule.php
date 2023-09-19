<?php
namespace modules\supersearch;
use modules\supersearch\variables\SuperSearchVariable;

use Craft;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;

use Meilisearch\Client as MeilisearchClient;
use Algolia\AlgoliaSearch\SearchClient as AlgoliaClient;

use craft\elements\Entry;
use craft\helpers\ElementHelper;
use craft\services\Search;
use craft\events\RegisterElementSearchableAttributesEvent;
use craft\events\IndexKeywordsEvent;
use craft\events\SearchEvent;
use craft\events\ModelEvent;
use craft\helpers\Search as SearchHelper;
use craft\helpers\StringHelper;
use craft\services\Elements;
use craft\base\Element;
use craft\events\DefineAttributeKeywordsEvent;
use craft\base\Field;
use craft\events\DefineFieldKeywordsEvent;
use craft\events\ElementEvent;
use craft\search\SearchQuery;
use craft\search\SearchQueryTerm;
use craft\search\SearchQueryTermGroup;

use craft\db\Query;
use craft\db\Table;
use yii\db\Expression;
use yii\db\Schema;


/**
 * This class will be available throughout the system via:
 * `Craft::$app->getModule('supersearch')`.
 */
class SuperSearchModule extends \yii\base\Module
{
  public function init()
  {
    // Set a @modules alias pointed to the modules/ directory
    Craft::setAlias('@modules', __DIR__);

    // Set the controllerNamespace based on whether this is a console or web request
    if (Craft::$app->getRequest()->getIsConsoleRequest()) {
        $this->controllerNamespace = 'modules\\supersearch\\console\\controllers';
    } else {
        $this->controllerNamespace = 'modules\\supersearch\\controllers';
    }

    parent::init();

     // Custom variables for use in Twig
    Event::on(
      CraftVariable::class,
      CraftVariable::EVENT_INIT,
      function (Event $event) {
        $variable = $event->sender;
        $variable->set('supersearch', SuperSearchVariable::class);
      }
    );

    // Indexing Events
    // $this->_registerBeforeUpdateSearchIndex();
    // $this->_registerRegisterSearchableAttributes();
    // $this->_registerDefineAttributeKeywords();
    // $this->_registerDefineFieldKeywords();
    // $this->_registerBeforeIndexKeywords();

    // Search Events
    //$this->_registerBeforeScoreResults();
    //$this->_registerAfterSearch();

    // External Search
    //$this->_registerIndexWithExternalServicesOnSave();

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
            // We can prevent the blank row from saving later on in EVENT_BEFORE_INDEX_KEYWORDS
          }
        }
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
        if ($attribute == 'menucategory' && !$keywords) {
          $event->isValid = false;
        }

        // If "hidden movement" appears in the "Game Tags" field - no it doesn't
        if ($fieldId == 12 && StringHelper::contains($keywords, 'hidden movement', false)) {
          $event->keywords = StringHelper::replaceAll($event->keywords, 'hidden movement', '', false);
          // If we wanted to prevent Game Tags fields containing "hidden movement" from ever being indexed, 
          // we could set this to false to prevent the row from saving
          $event->isValid = true;
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

        // If scores is set, Craft will NOT calculate scores
        $event->scores = [
          1133 => 1,
          796 => 2,
          1204 => 2
        ];
        return;

        // First we have to set up the terms and groups as Craft would do normally
        $this->_terms = [];
        $this->_groups = [];
        $searchQuery = $event->query;
        foreach ($searchQuery->getTokens() as $obj) {
            if ($obj instanceof SearchQueryTermGroup) {
                $this->_groups[] = $obj->terms;
            } else {
                $this->_terms[] = $obj;
            }
        }

        // Here we calculate scores and adjust results so that the Search service will skip doing that
        $results = [];
        $scores = [];

        // Loop through results and calculate score per element
        foreach ($event->results as $i => $row) {
          $elementId = $row['elementId'];
          $score = $this->_scoreRow($row, $event->siteId);
          $results[$i] = $row;

          if (!isset($scores[$elementId])) {
            $scores[$elementId] = $score;
          } else {
            $scores[$elementId] += $score;
          }
        }

        $event->results = $results;
        $event->results = $scores;
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

        // Go through all Craft-determined scores
        foreach($scores as $elementId => $score) {
          
          // Any result containing the keyword Everdell will have its score doubled
          $resultsMatchingThisScoreElement = array_filter($results, function($result) use ($elementId) {
            return $result['elementId'] == $elementId;
          });
          foreach ($resultsMatchingThisScoreElement as $result) {
            if (StringHelper::contains($result['keywords'], 'everdell', false)) {
              // Make sure score at least 1 (because 0*2=0)
              $event->scores[$elementId] = max([1, $scores[$elementId] * 2]);
            }
          }

          // Remove any results with a score of 0
          if ($scores[$elementId] < 1) {
            unset($event->scores[$elementId]);
            continue;
          }
        }
      
      }
    );
  }

  // ----------------------------------------------------------------------------------------------[ MODIFIED SCORING FUNCTIONS FROM CRAFT CORE ]

  /**
   * @var SearchQueryTerm[]
   */
  private array $_terms;

  /**
   * @var SearchQueryTerm[][]
   */
  private array $_groups;

  /**
   * Calculate score for a result.
   *
   * @param array $row A single result from the search query.
   * @param int|int[]|null $siteId
   * @return int The total score for this row.
   */
  private function _scoreRow(array $row, array|int|null $siteId = null): int
    {
      // Starting point
      $score = 0;

      // Loop through AND-terms and score each one against this row
      foreach ($this->_terms as $term) {
          $score += $this->_scoreTerm($term, $row, 1, $siteId);
      }

      // Loop through each group of OR-terms
      foreach ($this->_groups as $terms) {
          // OR-terms are weighted less depending on the amount of OR terms in the group
          $weight = 1 / count($terms);

          // Get the score for each term and add it to the total
          foreach ($terms as $term) {
              $score += $this->_scoreTerm($term, $row, $weight, $siteId);
          }
      }

      return (int)round($score);
  }

  /**
   * Calculate score for a row/term combination.
   *
   * @param SearchQueryTerm $term The SearchQueryTerm to score.
   * @param array $row The result row to score against.
   * @param float|int $weight Optional weight for this term.
   * @param int|int[]|null $siteId
   * @return float The total score for this term/row combination.
   */
  private function _scoreTerm(SearchQueryTerm $term, array $row, float|int $weight = 1, array|int|null $siteId = null): float
  {
      // Skip these terms: exact filtering is just that, no weighted search applies since all elements will
      // already apply for these filters.
      if ($term->exact || !($keywords = $this->_normalizeTerm($term->term, $siteId))) {
          return 0;
      }

      // Account for substrings
      if (!$term->subLeft) {
          $keywords = ' ' . $keywords;
      }

      if (!$term->subRight) {
          $keywords .= ' ';
      }

      // Get haystack and safe word count
      $haystack = $row['keywords'];
      $wordCount = count(array_filter(explode(' ', $haystack)));

      // Get number of matches
      $score = StringHelper::countSubstrings($haystack, $keywords);

      if ($score) {
          // Exact match
          if (trim($keywords) === trim($haystack)) {
              $mod = 100;
          } // Don't scale up for substring matches
          elseif ($term->subLeft || $term->subRight) {
              $mod = 10;
          } else {
              $mod = 50;
          }

          // If this is a title, 5X it
          if ($row['attribute'] === 'title') {
              $mod *= 50;
          }

          // If this is Favorite Games, also 5x it
          // if ($row['attribute'] === 'field' && $row['fieldId'] == 6) {
          //   $mod *= 10;
          // }

          // if ($term->term === 'everdell') { 
          //   $weight *= 15;
          // }

          $score = ($score / $wordCount) * $mod * $weight;
      }

      return $score;
  }

  /**
   * Normalize term from tokens, keep a record for cache.
   *
   * @param string $term
   * @param int|int[]|null $siteId
   * @return string
   */
  private function _normalizeTerm(string $term, array|int|null $siteId = null): string
  {
      static $terms = [];

      if (!array_key_exists($term, $terms)) {
          if ($siteId && !is_array($siteId)) {
              $site = Craft::$app->getSites()->getSiteById($siteId);
          }
          $terms[$term] = SearchHelper::normalizeKeywords($term, [], true, $site->language ?? null);
      }

      return $terms[$term];
  }


  // ----------------------------------------------------------------------------------------------[ ALGOLIA AND MEILISEARCH INTEGRATION ]
  // Save entries to Meilisearch and Algolia on save
  // Run Meilisearch with:  ~/Projects/meilisearch/meilisearch --master-key 58zmbORuI01yOiho0qNGO1xj5lK3D9KX76npjWEu2wQ
  // Reindex with:  php craft resave/entries --updateSearchIndex
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