<?php

namespace Drupal\privatemsg_migration_d7\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;

/**
 * Source plugin for retrieving private messages from DB.
 *
 * @MigrateSource(
 *   id = "privatemsg_message",
 *   source_module = "privatemsg"
 * )
 */
class PrivatemsgMessage extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $pm_fields = [
      'mid',
      'author',
      'subject',
      'body',
      'timestamp',
      'format',
    ];
    $query = $this->select('pm_message', 'pm');
    $query->fields('pm', $pm_fields);
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'mid' => $this->t('Private Message ID'),
      'author' => $this->t('Author'),
      'subject' => $this->t('Subject'),
      'body' => $this->t('Body'),
      'timestamp' => $this->t('Timestamp'),
      'format' => $this->t('Format'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'mid' => [
        'type' => 'integer',
        'alias' => 'pm',
      ],
    ];
  }

}
