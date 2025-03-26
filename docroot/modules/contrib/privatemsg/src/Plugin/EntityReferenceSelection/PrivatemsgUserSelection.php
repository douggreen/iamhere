<?php

namespace Drupal\privatemsg\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:privatemsg",
 *   label = @Translation("Privatemsg user selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 0
 * )
 */
class PrivatemsgUserSelection extends UserSelection {

}
