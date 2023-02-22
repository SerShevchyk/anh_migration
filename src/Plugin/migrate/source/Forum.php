<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\migrate\Annotation\MigrateSource;
use Drupal\migrate\Row;
use Drupal\migrate_drupal\Plugin\migrate\source\d7\FieldableEntity;


/**
 * Extract forum from Drupal 7 database.
 *
 * @MigrateSource(
 *   id = "anh_migration_forum",
 * )
 */
class Forum extends FieldableEntity {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('node', 'n')
      ->fields('n', [
        'nid',
        'type',
        'title',
        'uid',
        'status',
        'created',
        'sticky',
        'changed',
        'promote',
        'comment',
      ])
      ->condition('n.type', ['forum'], 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    foreach (array_keys($this->getFields('node', 'forum')) as $field) {
      $nid = $row->getSourceProperty('nid');
      $vid = $row->getSourceProperty('vid');

      $row->setSourceProperty($field, $this->getFieldValues('node', $field, $nid, $vid));
    }
    // Make sure we always have a translation set.
    if ($row->getSourceProperty('tnid') == 0) {
      $row->setSourceProperty('tnid', $row->getSourceProperty('nid'));
    }

    // body
    $query = $this->select('field_data_body','fdb')
      ->fields('fdb', ['body_value', 'body_summary', 'body_format'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $body = $query['body_value'];
    while (preg_match('/\[\[.*]]/', $body, $matches)) {
      $matches  = array_shift($matches);
      $image = json_decode($matches);
      $image = $image[0][0];
      $query = $this->select('file_managed', 'fm')
        ->fields('fm')
        ->condition('fid', $image->fid)
        ->execute()
        ->fetch();
      $uri = $query['uri'];
      $src = $uri ? 'src="' . str_replace('public://', '/sites/default/files/', $uri) . '"' : NULL;

      $class = $image->attributes->class;
      $class = $class ? 'class="' . $class . '"' : NULL;

      $style = $image->attributes->style;
      $style = $style ? 'style="' . $style . '"' : NULL;

      $html = "<img $class $src $style />";
      $body = preg_replace('/\[\[.*]]/', $html, $body, '1' );
    }
    $row->setSourceProperty('body_value', $body);
    $row->setSourceProperty('body_summary', $query['body_summary']);
    $row->setSourceProperty('body_format', 'full_html');

    // taxonomy_forums
//    $query = $this->select('field_data_taxonomy_forums','fdtf')
//      ->fields('fdtf', ['taxonomy_forums_tid'])
//      ->condition('entity_id', $nid)
//      ->execute()
//      ->fetch();
//    $row->setSourceProperty('taxonomy_forums', $query['taxonomy_forums_tid']);

    // field_forum_agree
    $query = $this->select('field_data_field_i_agree_to_my_comment_bein','fdfiatmcb')
      ->fields('fdfiatmcb', ['field_i_agree_to_my_comment_bein_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_forum_agree', $query['field_i_agree_to_my_comment_bein_value']);

    // field_academy
    $query = $this->select('domain_access', 'da')
      ->fields('da', ['gid'])
      ->condition('nid', $nid)
      ->execute()
      ->fetchCol();
    $result = [];
    foreach ($query as $item) {
      switch ($item) {
        case '1':
          break;

        case '2':
          $result[] = '2';
          break;

        case '3':
          $result[] = '1';
          break;
      }
    }
    $row->setSourceProperty('field_academy', $result);

    // field_forum_attachment
    $query = $this->select('field_data_field_forum_attachment','fdffa')
      ->fields('fdffa', ['field_forum_attachment_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_forum_attachment', $query);

    return parent::prepareRow($row);
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('Node ID'),
      'type' => $this->t('Type'),
      'title' => $this->t('Title'),
      'body_value' => $this->t('Full text of body'),
      'body_format' => $this->t('Format of body'),
      'node_uid' => $this->t('Node authored by (uid)'),
      'revision_uid' => $this->t('Revision authored by (uid)'),
      'created' => $this->t('Created timestamp'),
      'changed' => $this->t('Modified timestamp'),
      'status' => $this->t('Published'),
      'promote' => $this->t('Promoted to front page'),
      'sticky' => $this->t('Sticky at top of lists'),
      'comment' => $this->t('Sticky at top of lists'),
      'revision' => $this->t('Create new revision'),
      'language' => $this->t('Language (fr, en, ...)'),
      'tnid' => $this->t('The translation set id for this node'),
      'timestamp' => $this->t('The timestamp the latest revision of this node was created.'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids['nid']['type'] = 'integer';
    $ids['nid']['alias'] = 'n';
    return $ids;
  }

  /**
   * Adapt our query for translations.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The generated query.
   */
  protected function handleTranslations(SelectInterface $query) {
    // Check whether or not we want translations.
    if (empty($this->configuration['translations'])) {
      // No translations: Yield untranslated nodes, or default translations.
      $query->where('n.tnid = 0 OR n.tnid = n.nid');
    }
    else {
      // Translations: Yield only non-default translations.
      $query->where('n.tnid <> 0 AND n.tnid <> n.nid');
    }
  }
}
