<?php

namespace modules\supersearch\console\controllers;

use Craft;
use craft\db\Table;
use craft\console\Controller;
use craft\helpers\Console;
use yii\console\ExitCode;

use Meilisearch\Client as MeilisearchClient;
use Algolia\AlgoliaSearch\SearchClient as AlgoliaClient;

class IndexController extends Controller
{
    public $defaultAction = 'wipe';

    public function actionWipe(): int
    {
        // Clear local searchindex
        $db = Craft::$app->getDb();
        $db->createCommand()->truncateTable(Table::SEARCHINDEX)->execute();

        // Clear Meilisearch
        $meilisearchClient = new MeilisearchClient(getenv('MEILISEARCH_DOMAIN'), getenv('MEILISEARCH_KEY'));
        $meilisearchClient->index(getenv('EXTERNAL_SEARCH_INDEX'))->deleteAllDocuments();

        // Clear Algolia
        $algoliaClient = AlgoliaClient::create(getenv('ALGOLIA_APPLICATION_ID'), getenv('ALGOLIA_ADMIN_API_KEY'));
        $algoliaIndex = $algoliaClient->initIndex(getenv('EXTERNAL_SEARCH_INDEX'));
        $algoliaIndex->clearObjects();

        $this->stdout("Local, Meilisearch, and Algolia indices have been cleared!", Console::FG_GREEN);

        return ExitCode::OK;
    }
}