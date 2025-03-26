<?php

namespace Drupal\privatemsg\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:privatemsg_blocked",
 *   label = @Translation("Privatemsg user blocked selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 0
 * )
 */
class PrivatemsgUserBlockedSelection extends UserSelection {

}
