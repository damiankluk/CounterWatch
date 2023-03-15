<?php

namespace Drupal\co_best_picks\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Annotation\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a Best picks Resource.
 *
 * @RestResource(
 *   id = "best_picks_resource",
 *   label = @Translation("Best picks"),
 *   uri_paths = {
 *     "canonical" = "/api/best_picks/{names}"
 *   }
 * )
 */
class BestPicksResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new BestPicksResource object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    \Psr\Log\LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition,
      $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('co_best_picks'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param string $names
   *   A comma-separated list of character names.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the character counts.
   */
  public function get(string $names = '') :ResourceResponse {
    $name_array = explode(',', $names);

    if (empty($name_array)) {
      throw new BadRequestHttpException('At least one character name is required.');
    }

    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $hero_storage = $nodeStorage
      ->loadByProperties([
        'title' => $name_array,
        'type' => 'hero'
    ]);

    $counts = [];
    foreach ($hero_storage as $hero) {
      $best_pics = $hero->get('field_counter_pics')->getValue();
      foreach ($best_pics as $best_pic) {
        $counterNode = $nodeStorage->load($best_pic['target_id']);

        $character_name = $counterNode->title->value;

          if (!isset($counts[$character_name])) {
            $counts[$character_name] = 0;
          }
          $counts[$character_name]++;
      }
    }

    arsort($counts, SORT_NUMERIC);

    $response = new ResourceResponse($counts);
    $response->addCacheableDependency($counts);
    return $response;
  }
}