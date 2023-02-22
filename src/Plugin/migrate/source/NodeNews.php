<?php

namespace Drupal\anh_migration\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * @MigrateSource(
 *   id = "node_news"
 * )
 */
class NodeNews extends SqlBase {

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
        'changed',
      ])
      ->condition('n.type', ['academy_news_story', 'immana_news_story', 'story'], 'IN');
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'nid' => $this->t('The primary identifier for a node'),
      'type' => $this->t('The node_type.type of this node'),
      'title' => $this->t('The title of this node, always treated as non-markup plain text'),
      'uid' => $this->t('The users.uid that owns this node; initially, this is the user that created id'),
      'status' => $this->t('Boolean indicating whether the node is published (visible to non-administrators)'),
      'created' => $this->t('The Unix timestamp when the node was created'),
      'changed' => $this->t('The Unix timestamp when the node was most recently saved.'),
      'promote' => $this->t('Add slide to home pages'),
      // Custom added fields.
      'field_news_body' => $this->t('Body'),
      'field_news_file_attachments' => $this->t('File attachments'),
      'field_news_image' => $this->t('Image'),
      'field_news_paragraphs' => $this->t('Paragraphs'),
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'nid' => [
        'type' => 'integer',
        'alias' => 'n',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $nid = $row->getSourceProperty('nid');

    // field_news_slide_home_pages
    $query = $this->select('field_data_field_add_slide_to_home_page', 'fdfasthp')
      ->fields('fdfasthp', ['field_add_slide_to_home_page_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    if (!empty($query)) {
      $row->setSourceProperty('promote', '1');
    }

    // field_news_body
    $query = $this->select('field_data_body', 'fdb')
      ->fields('fdb', ['body_value', 'body_summary'])
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
    $row->setSourceProperty('field_news_body_value', $body);
    $row->setSourceProperty('field_news_body_summary', $query['body_summary']);
    $row->setSourceProperty('field_news_body_format', 'full_html');

    // field_news_image
    $query = $this->select('field_data_field_slide_image', 'fdfsi')
      ->fields('fdfsi', ['field_slide_image_fid'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetch();
    $row->setSourceProperty('field_news_image', $query['field_slide_image_fid']);

    // field_news_paragraphs
    $paragraph_ids = $this->select('field_data_field_paragraphs', 'fdfp')
      ->fields('fdfp', ['field_paragraphs_value'])
      ->condition('entity_id', $nid)
      ->execute()
      ->fetchCol();
    foreach ($paragraph_ids as $id) {
      $query = $this->select('paragraphs_item', 'pi')
        ->fields('pi', ['bundle'])
        ->condition('item_id', $id)
        ->execute()
        ->fetch();
      switch ($query['bundle']) {
        case 'centred_image':
          // field_ci_centred_image
          $query = $this->select('field_data_field_centred_image', 'fdfci')
            ->fields('fdfci', ['field_centred_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_centred_image_fid'])
            ->execute()
            ->fetch();
          $field_ci_centred_image = $new_image_id->destid1;

          // field_ci_link
          $query = $this->select('field_data_field_para_link', 'fdfpl')
            ->fields('fdfpl', ['field_para_link_url'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_ci_link = $query['field_para_link_url'];

          $paragraph_options = [
            'type' => 'centered_image',
            'language' => 'en',
            'field_centered_image_image' => [
              'target_id' => $field_ci_centred_image,
            ],
            'field_centered_image_link' => [
              'uri' => $field_ci_link,
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;

        case 'image_left_text_right':
          // field_iltr_paragraph_image
          $query = $this->select('field_data_field_paragraph_image', 'fdfpi')
            ->fields('fdfpi', ['field_paragraph_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_paragraph_image_fid'])
            ->execute()
            ->fetch();
          $field_iltr_paragraph_image = $new_image_id->destid1;

          // field_iltr_image_link
          $query = $this->select('field_data_field_link_to_content', 'fdfltc')
            ->fields('fdfltc', ['field_link_to_content_url'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_iltr_image_link = $query['field_link_to_content_url'];

          // field_iltr_paragraph_text
          $query = $this->select('field_data_field_paragraph_text', 'fdfpt')
            ->fields('fdfpt', ['field_paragraph_text_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_iltr_paragraph_text = $query['field_paragraph_text_value'];

          $paragraph_options = [
            'type' => 'image_text',
            'language' => 'en',
            'field_image_text_image' => [
              'target_id' => $field_iltr_paragraph_image,
            ],
            'field_image_text_link' => [
              'uri' => $field_iltr_image_link,
            ],
            'field_image_text_text' => [
              'value' => $field_iltr_paragraph_text,
              'format' => 'full_html',
            ],
            'field_image_text_image_position' => [
              'value' => 'Image left text right'
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;

        case 'one_column_centred_text':
          // field_oct_centred_paragraph_text
          $query = $this->select('field_data_field_paragraph_text', 'fdfpt')
            ->fields('fdfpt', ['field_paragraph_text_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();

          $body = $query['field_paragraph_text_value'];
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

          $paragraph_options = [
            'type' => 'text',
            'language' => 'en',
            'field_text_text' => [
              'value' => $body,
              'format' => 'full_html',
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;

        case 'one_column_text':
          // field_ct_paragraph_text
          $query = $this->select('field_data_field_paragraph_text', 'fdfpt')
            ->fields('fdfpt', ['field_paragraph_text_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();

          $body = $query['field_paragraph_text_value'];
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

          $paragraph_options = [
            'type' => 'text',
            'language' => 'en',
            'field_text_text' => [
              'value' => $body,
              'format' => 'full_html',
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;

        case 'text_left_image_right':
          // field_tlir_paragraph_text
          $query = $this->select('field_data_field_paragraph_text', 'fdfpt')
            ->fields('fdfpt', ['field_paragraph_text_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_tlir_paragraph_text = $query['field_paragraph_text_value'];

          // field_tlir_paragraph_image
          $query = $this->select('field_data_field_paragraph_image', 'fdfpi')
            ->fields('fdfpi', ['field_paragraph_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_paragraph_image_fid'])
            ->execute()
            ->fetch();
          $field_tlir_paragraph_image = $new_image_id->destid1;

          $paragraph_options = [
            'type' => 'image_text',
            'language' => 'en',
            'field_image_text_text' => [
              'value' => $field_tlir_paragraph_text,
              'format' => 'full_html',
            ],
            'field_image_text_image' => [
              'target_id' => $field_tlir_paragraph_image,
            ],
            'field_image_text_image_position' => [
              'value' => 'Text left image right'
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;

        case 'three_columns_text_and_images':
          // field_3cti_left_image
          $query = $this->select('field_data_field_left_image', 'fdfli')
            ->fields('fdfli', ['field_left_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_left_image_fid'])
            ->execute()
            ->fetch();
          $field_3cti_left_image = $new_image_id->destid1;

          // field_3cti_left_image_link
          $query = $this->select('field_data_field_left_image_link', 'fdflil')
            ->fields('fdflil', ['field_left_image_link_url'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_left_image_link = $query['field_left_image_link_url'];

          // field_3cti_left_text
          $query = $this->select('field_data_field_para_column_one', 'fdfpco')
            ->fields('fdfpco', ['field_para_column_one_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_left_text = $query['field_para_column_one_value'];

          // field_3cti_middle_image
          $query = $this->select('field_data_field_middle_image', 'fdfmi')
            ->fields('fdfmi', ['field_middle_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_middle_image_fid'])
            ->execute()
            ->fetch();
          $field_3cti_middle_image = $new_image_id->destid1;

          // field_3cti_middle_image_link
          $query = $this->select('field_data_field_middle_image_link', 'fdfmil')
            ->fields('fdfmil', ['field_middle_image_link_url'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_middle_image_link = $query['field_middle_image_link_url'];

          // field_3cti_middle_text
          $query = $this->select('field_data_field_para_column_two', 'fdfpct')
            ->fields('fdfpct', ['field_para_column_two_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_middle_text = $query['field_para_column_two_value'];

          // field_3cti_right_image
          $query = $this->select('field_data_field_right_image', 'fdfri')
            ->fields('fdfri', ['field_right_image_fid'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $new_image_id = \Drupal::database()
            ->select('migrate_map_images_to_media', 'mmitm')
            ->fields('mmitm', ['destid1'])
            ->condition('sourceid1', $query['field_right_image_fid'])
            ->execute()
            ->fetch();
          $field_3cti_right_image = $new_image_id->destid1;

          // field_3cti_right_image_link
          $query = $this->select('field_data_field_right_image_link', 'fdfril')
            ->fields('fdfril', ['field_right_image_link_url'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_right_image_link = $query['field_right_image_link_url'];

          // field_3cti_right_text
          $query = $this->select('field_data_field_para_column_three', 'fdfpcth')
            ->fields('fdfpcth', ['field_para_column_three_value'])
            ->condition('entity_id', $id)
            ->execute()
            ->fetch();
          $field_3cti_right_text = $query['field_para_column_three_value'];

          $paragraph_options = [
            'type' => 'three_columns_text_images',
            'language' => 'en',
            'field_tcti_left_image' => [
              'target_id' => $field_3cti_left_image,
            ],
            'field_tcti_left_image_link' => [
              'uri' => $field_3cti_left_image_link,
            ],
            'field_tcti_left_text' => [
              'value' => $field_3cti_left_text,
              'format' => 'full_html',
            ],
            'field_tcti_middle_image' => [
              'target_id' => $field_3cti_middle_image,
            ],
            'field_tcti_middle_image_link' => [
              'uri' => $field_3cti_middle_image_link,
            ],
            'field_tcti_middle_text' => [
              'value' => $field_3cti_middle_text,
              'format' => 'full_html',
            ],
            'field_tcti_right_image' => [
              'target_id' => $field_3cti_right_image,
            ],
            'field_tcti_right_image_link' => [
              'uri' => $field_3cti_right_image_link,
            ],
            'field_tcti_right_text' => [
              'value' => $field_3cti_right_text,
              'format' => 'full_html',
            ],
          ];

          $paragraph = Paragraph::create($paragraph_options);
          $paragraph->save();
          $result[] = $paragraph;
          break;
      }
    }
    $row->setSourceProperty('field_news_paragraphs', $result);

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

    return parent::prepareRow($row);
  }

}
