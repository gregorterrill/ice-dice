<?php

namespace modules\supersearch\controllers;

use Craft;
use yii\web\Response;
use craft\web\Controller;
use craft\elements\Entry;
use modules\supersearch\variables\SuperSearchVariable;

class SearchController extends Controller
{
  protected array|int|bool $allowAnonymous = true;

  public function actionGetSearchResults(): ?Response
  {
    $this->requirePostRequest();
    $query = $this->request->getParam('query');

    // Do the query
    $results = Entry::find()
      ->search($query)
      ->orderBy('score DESC')
      ->limit(5)
      ->collect();

    // Use the front-end variable to grab our related icons
    $variable = new SuperSearchVariable;

    // Limit the response to just the fields we need, plus our icon
    $response = $results->map(function ($item, $key) use ($variable) {
      return [
        'title' => $item->title,
        'url' => $variable->getResultLink($item),
        'section' => $item->section->name,
        'icon' => $variable->icon($item->section->handle, 20)
      ];
    });

    return $this->asJson($response);
  }
}