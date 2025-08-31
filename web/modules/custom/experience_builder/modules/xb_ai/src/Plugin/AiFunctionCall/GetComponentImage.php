<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
use Drupal\ai\Utility\ContextDefinitionNormalizer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Function call plugin to get an image from the media library.
 *
 * For MVP, this tool ignores any provided keywords and returns a randomly
 * selected image from the Drupal Media Library to enable rapid prototyping.
 *
 * @internal
 */
#[FunctionCall(
  id: 'xb_ai:get_component_image',
  function_name: 'get_component_image',
  name: 'Get Component Image',
  description: 'Retrieves an image from the media library, optionally based on keywords. For MVP, returns random images to enable rapid prototyping.',
  group: 'information_tools',
  module_dependencies: ['xb_ai'],
  context_definitions: [
    'keywords' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Keywords"),
      description: new TranslatableMarkup("Descriptive keywords for the desired image (currently ignored for MVP)."),
      required: FALSE,
    ),
  ],
)]
final class GetComponentImage extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface | static {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      new ContextDefinitionNormalizer(),
    );
    $instance->currentUser = $container->get('current_user');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->fileUrlGenerator = $container->get('file_url_generator');
    $instance->loggerFactory = $container->get('logger.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    // Check user permissions.
    if (!$this->currentUser->hasPermission('use experience builder ai')) {
      throw new \Exception('The current user does not have permission to use this tool.');
    }

    $keywords = $this->getContextValue('keywords') ?? '';
    $this->loggerFactory->get('xb_ai')->info('GetComponentImage called with keywords: @keywords', ['@keywords' => $keywords]);

    try {
      // For the MVP, we ignore keywords and select a random image.
      $query = $this->entityTypeManager->getStorage('media')->getQuery()
        ->condition('bundle', 'image')
        ->condition('status', 1)
        ->accessCheck(TRUE)
        ->range(0, 1);

      // Add random ordering - different methods based on database backend
      $query->sort('created', 'DESC');
      $query->range(0, 50); // Get 50 recent images first

      $ids = $query->execute();

      if (empty($ids)) {
        $this->loggerFactory->get('xb_ai')->warning('No suitable images found in the media library');
        $this->setOutput(Yaml::dump([
          'error' => 'No suitable images were found in the media library.',
          'suggestion' => 'Please ensure you have published image media entities in your media library.',
        ], 4, 2));
        return;
      }

      // Pick a random ID from the results for MVP randomization
      $random_id = array_rand($ids);
      $media_id = $ids[$random_id];

      /** @var \Drupal\media\Entity\Media $media */
      $media = $this->entityTypeManager->getStorage('media')->load($media_id);

      if (!$media || !$media->hasField('field_media_image')) {
        $this->loggerFactory->get('xb_ai')->error('Media entity @id not found or missing image field', ['@id' => $media_id]);
        $this->setOutput(Yaml::dump(['error' => 'Selected media entity is invalid or missing image field.'], 4, 2));
        return;
      }

      $image_field = $media->get('field_media_image')->first();
      
      if (!$image_field || !$image_field->entity) {
        $this->loggerFactory->get('xb_ai')->error('Image field is empty for media @id', ['@id' => $media_id]);
        $this->setOutput(Yaml::dump(['error' => 'Selected media entity has no image file.'], 4, 2));
        return;
      }

      $file_entity = $image_field->entity;
      $file_uri = $file_entity->getFileUri();

      // Get image dimensions if available
      $width = $image_field->width ?? null;
      $height = $image_field->height ?? null;

      $result = [
        'url' => $this->fileUrlGenerator->generateAbsoluteString($file_uri),
        'alt' => $image_field->alt ?: 'Image from media library',
        'media_id' => (int) $media_id,
        'filename' => $file_entity->getFilename(),
        'filesize' => $file_entity->getSize(),
        'mime_type' => $file_entity->getMimeType(),
      ];

      // Add dimensions if available
      if ($width && $height) {
        $result['width'] = (int) $width;
        $result['height'] = (int) $height;
      }

      $this->loggerFactory->get('xb_ai')->info('Successfully retrieved image: @filename (Media ID: @id)', [
        '@filename' => $result['filename'],
        '@id' => $media_id,
      ]);

      $this->setOutput(Yaml::dump($result, 4, 2));

    } catch (\Exception $e) {
      $this->loggerFactory->get('xb_ai')->error('Error retrieving component image: @error', ['@error' => $e->getMessage()]);
      throw new \Exception('Error retrieving component image: ' . $e->getMessage());
    }
  }

}