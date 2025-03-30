<?php

/**
 * @file
 * Hooks for Field Encrypt module.
 */

use Drupal\node\Entity\Node;

/**
 * Hook to specify if a given entity should be encrypted.
 *
 * Allows other modules to specify whether an entity should not be encrypted by
 * field_encrypt module, regardless of the field encryption settings.
 *
 * If conditions are met where an entity should not be encrypted, return FALSE
 * in your hook implementation.
 *
 * Note: this only stops the encryption of an entity that was set up to be
 * encrypted. It does not allow an entity that is not configured to be
 * encrypted, because there are no settings defined to do so.
 *
 * @param \Drupal\Core\Entity\ContentEntityInterface $entity
 *   The entity to encrypt fields on.
 *
 * @return false
 *   Return FALSE if entity should not be encrypted.
 */
function hook_field_encrypt_allow_encryption(\Drupal\Core\Entity\ContentEntityInterface $entity) {
  // Only encrypt fields on unpublished nodes.
  if ($entity instanceof Node) {
    if ($entity->isPublished()) {
      return FALSE;
    }
  }
}
