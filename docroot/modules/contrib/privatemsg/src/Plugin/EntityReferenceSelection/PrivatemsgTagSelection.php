<?php

namespace Drupal\privatemsg\Plugin\EntityReferenceSelection;

use Drupal\taxonomy\Plugin\EntityReferenceSelection\TermSelection;

/**
 * Provides specific access control for the taxonomy_term entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:privatemsg_tag",
 *   label = @Translation("Privatemsg tag selection"),
 *   entity_types = {"taxonomy_term"},
 *   group = "default",
 *   weight = 0
 * )
 */
class PrivatemsgTagSelection extends TermSelection {

}
