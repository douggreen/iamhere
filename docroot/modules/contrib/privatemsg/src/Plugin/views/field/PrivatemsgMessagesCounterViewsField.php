<?php

namespace Drupal\privatemsg\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * A handler to provide a field that is completely custom by the administrator.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("privatemsg_messages_counter_views_field")
 */
class PrivatemsgMessagesCounterViewsField extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing -- to override the parent query.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $thread = $this->getEntity($values);
    if ($thread) {
      return $thread->getMessagesCount();
    }
    return NULL;
  }

}
