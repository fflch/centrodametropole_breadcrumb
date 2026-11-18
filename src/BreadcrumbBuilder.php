<?php

namespace Drupal\centrodametropole_custom_breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Menu\MenuActiveTrail;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\path_alias\AliasManagerInterface;

class BreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  protected $menuActiveTrail;
  protected $entityTypeManager;
  protected $configFactory;
  protected $pathValidator;
  protected $aliasManager;
  protected $menuLinkManager;
  protected $cacheMenu;
  protected $lock;
  protected $entity_id;

  public function __construct(
  MenuActiveTrailInterface $menu_active_trail,
  EntityTypeManagerInterface $entity_type_manager,
  ConfigFactoryInterface $config_factory,
  PathValidatorInterface $path_validator,
  AliasManagerInterface $alias_manager,
  MenuLinkManagerInterface $menu_link_manager,
  CacheBackendInterface $cache_menu,
  LockBackendInterface $lock
  ) {
    $this->menuActiveTrail = $menu_active_trail;
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->pathValidator = $path_validator;
    $this->aliasManager = $alias_manager;
    $this->menuLinkManager = $menu_link_manager;
    $this->cacheMenu = $cache_menu;
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {

    // No route name means no active trail:
    $route_name = $route_match->getRouteName();

    if (!$route_name) {
      return FALSE;
    }

    if ($route_name == 'valida_email.form') {
      return FALSE;
    }

    if ($route_name == 'entity.node.canonical') {
      $this->entity_id = $route_match->getRawParameters()->all()['node'];
      if (!empty($this->entity_id) && is_numeric($this->entity_id)) {
        $entity = $this->entityTypeManager->getStorage('node')->load($this->entity_id);
        if ($entity && in_array($entity->bundle(), ['article', 'pessoas'])) {
          return FALSE;
        }
      }
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumb = new Breadcrumb();

    $links = [];
    $breadcrumb->addCacheContexts(['route.menu_active_trails:' . 'menu-lateral']);
    $breadcrumb->addCacheContexts(['url.path']);

    $menuActiveTrail = new MenuActiveTrail($this->menuLinkManager, $route_match, $this->cacheMenu, $this->lock);
    $home_link = Link::createFromRoute($this->t('Home'), '<front>');
    $trail_ids = $menuActiveTrail->getActiveTrailIds('main');
    $trail_ids = array_filter($trail_ids);
    foreach (array_reverse($trail_ids) as $id) {
      $plugin = $this->menuLinkManager->createInstance($id);
      $id = $plugin->getUrlObject()->getRouteParameters();
      if ($id != $route_match->getRawParameters()->all()) {
        $links[] = Link::fromTextAndUrl($plugin->getTitle(), $plugin->getUrlObject());
      }
      else {
        $title = $plugin->getTitle();
      }
    }
    array_unshift($links, $home_link);
    $breadcrumb->setLinks($links);
    if(isset($title)) {
      $breadcrumb->addLink(Link::createFromRoute($title, '<none>'));
    }

    return $breadcrumb;
  }
}
