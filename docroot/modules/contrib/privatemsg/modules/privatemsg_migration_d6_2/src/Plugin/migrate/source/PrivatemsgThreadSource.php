<?php

namespace Drupal\privatemsg_migration_d6_2\Plugin\migrate\source;

use Drupal\migrate\Plugin\migrate\source\SqlBase;
use Drupal\migrate\Row;

/**
 * Source plugin for retrieving threads from DB.
 *
 * @MigrateSource(
 *   id = "privatemsg_thread_source",
 *   source_module = "privatemsg"
 * )
 */
class PrivatemsgThreadSource extends SqlBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $query = $this->select('pm_index', 'pmi');
    $query->join('pm_message', 'm', 'pmi.mid = m.mid');
    $query->fields('pmi', ['thread_id']);
    $query->groupBy('pmi.thread_id');

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    $fields = [
      'mids' => $this->t('Message IDs'),
      'members' => $this->t('Members'),
      'subject' => $this->t('Thread subject'),
      'updated_custom' => $this->t('Thread updated timestamp'),
      'tags' => $this->t('Thread tags'),
      'is_new' => $this->t('Is thread read'),
    ];

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    return [
      'thread_id' => [
        'type' => 'integer',
        'alias' => 'pmi',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRow(Row $row) {
    $thread_id = $row->getSourceProperty('thread_id');

    $thread_details = $this->select('pm_index', 'pmi')
      ->fields('pmi', ['recipient', 'mid', 'is_new'])
      ->condition('thread_id', $thread_id)
      ->orderBy('pmi.mid')
      ->execute()
      ->fetchAll();
    $members = [];
    $mids = [];
    $is_new = [];

    foreach ($thread_details as $thread) {
      $members[$thread['recipient']] = $thread['recipient'];
      $mids[$thread['mid']] = $thread['mid'];
      if ($thread['is_new'] === '1') {
        $is_new[$thread['recipient']] = 1;
      }
    }
    $row->setSourceProperty('is_new', $is_new);

    $row->setSourceProperty('members', array_values($members));

    $row->setSourceProperty('mids', array_values($mids));
    $last_mid = end($mids);

    $message_details = $this->select('pm_message', 'pmm')
      ->fields('pmm', ['subject', 'timestamp'])
      ->condition('mid', $last_mid)
      ->execute()
      ->fetchAll();
    $message_details = reset($message_details);

    $row->setSourceProperty('subject', $message_details['subject']);
    $row->setSourceProperty('updated_custom', $message_details['timestamp']);

    if ($this->database->schema()->tableExists('pm_tags_index')) {
      $query = $this->select('pm_tags_index', 'pmti');
      $query->innerJoin('pm_tags', 'pmt', 'pmti.tag_id = pmt.tag_id');
      $query->fields('pmti', ['uid', 'thread_id']);
      $query->fields('pmt', ['tag']);
      $query->condition('thread_id', $thread_id);
      $tags = $query->execute()->fetchAll();
      $row->setSourceProperty('tags', array_values($tags));
    }
    else {
      $row->setSourceProperty('tags', []);
    }

    return parent::prepareRow($row);
  }

}
