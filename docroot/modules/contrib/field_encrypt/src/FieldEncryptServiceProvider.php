<?php

declare(strict_types=1);

namespace Drupal\field_encrypt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Orders hooks to ensure entities are decrypted before use.
 */
class FieldEncryptServiceProvider extends ServiceProviderBase {

  const string METHOD_NAME = 'decryptEntity';

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $field_encrypt_entity_types = $container->get('keyvalue')
      ->get('field_encrypt')
      ->get('entity_types', []);
    $container->getDefinition('field_encrypt.state_manager')->setArgument('$entityTypes', $field_encrypt_entity_types);
    $container->setParameter('field_encrypt.entity_types', $field_encrypt_entity_types);
    if (!empty($field_encrypt_entity_types)) {
      $map = $container->getParameter('hook_implementations_map');
      $definition = $container->findDefinition('field_encrypt.process_entities');
      $class = $definition->getClass();
      foreach ($field_encrypt_entity_types as $entity_type) {
        $hooks = [
          "{$entity_type}_insert",
          "{$entity_type}_update",
        ];
        foreach ($hooks as $hook) {
          $definition->addTag('kernel.event_listener', [
            'event' => "drupal_hook.$hook",
            'method' => self::METHOD_NAME,
            'priority' => PHP_INT_MAX,
          ]);
          $map[$hook][$class][self::METHOD_NAME] = 'field_encrypt';
        }
      }
      $container->setParameter('hook_implementations_map', $map);
    }
  }

}
