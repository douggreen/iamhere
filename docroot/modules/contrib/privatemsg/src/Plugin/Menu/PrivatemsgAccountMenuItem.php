<?php

namespace Drupal\privatemsg\Plugin\Menu;

use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\privatemsg\PrivateMsgService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a implementation for menu link plugin.
 */
class PrivatemsgAccountMenuItem extends MenuLinkDefault {

  /**
   * The privatemsg.common service.
   */
  protected PrivateMsgService $common;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, StaticMenuLinkOverridesInterface $static_override, PrivateMsgService $common) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
    $this->common = $common;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('privatemsg.common'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    $count = $this->common->getUnreadThreadCount();
    $text = $this->t('Private Messages');
    if ($count > 0) {
      return $text . " ($count)";
    }
    return $text;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}
