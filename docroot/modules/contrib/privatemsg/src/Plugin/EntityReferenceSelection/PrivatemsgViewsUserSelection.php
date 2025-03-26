<?php

namespace Drupal\privatemsg\Plugin\EntityReferenceSelection;

use Drupal\user\Plugin\EntityReferenceSelection\UserSelection;

/**
 * Provides specific access control for the user entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:privatemsg_views",
 *   label = @Translation("Privatemsg views user selection"),
 *   entity_types = {"user"},
 *   group = "default",
 *   weight = 0
 * )
 */
class PrivatemsgViewsUserSelection extends UserSelection {

}
