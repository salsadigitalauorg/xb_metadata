<?php

namespace Drupal\xb_ai\Service;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Manages the discovery and caching of component schema files.
 */
final class XbAiComponentSchemaManager {

  private const CACHE_ID = 'xb_ai:component_schema_map';
  
  private const SCHEMA_DIRECTORY = '/themes/contrib/civictheme_xb/schema/components/';

  private ?array $schemaMap = NULL;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   The file system service.
   */
  public function __construct(
    private readonly CacheBackendInterface $cache,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly FileSystemInterface $fileSystem
  ) {}

  /**
   * Gets the schema data for a specific component.
   *
   * @param string $component_id
   *   The component ID.
   *
   * @return array|null
   *   The component schema data, or NULL if not found.
   */
  public function getSchemaForComponent(string $component_id): ?array {
    # Component id is usually something like sdc.civictheme_xb.image but the schema will be image.json
    # We need to extract the base name (e.g., image) from the component id
    $base_name = basename(str_replace('.', '/', $component_id));
    $schema_path = $this->getSchemaPath($base_name);
    if (!$schema_path || !file_exists($schema_path)) {
      $this->loggerFactory->get('xb_ai')->warning('Schema file not found for component: @component_id', ['@component_id' => $component_id]);
      return NULL;
    }

    try {
      $schema_content = file_get_contents($schema_path);
      $schema_data = Json::decode($schema_content);
      
      $this->loggerFactory->get('xb_ai')->info('Successfully loaded schema for component: @component_id', ['@component_id' => $component_id]);
      return $schema_data;
    } catch (\Exception $e) {
      $this->loggerFactory->get('xb_ai')->error('Error loading schema for component @component_id: @error', [
        '@component_id' => $component_id,
        '@error' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

  /**
   * Gets multiple component schemas efficiently.
   *
   * @param array $component_ids
   *   Array of component IDs.
   *
   * @return array
   *   Array of component schemas keyed by component ID.
   */
  public function getMultipleSchemas(array $component_ids): array {
    $schemas = [];
    
    foreach ($component_ids as $component_id) {
      $schema = $this->getSchemaForComponent($component_id);
      if ($schema !== NULL) {
        $schemas[$component_id] = $schema;
      }
    }
    
    return $schemas;
  }

  /**
   * Gets the full path to a component's schema file.
   *
   * @param string $component_id
   *   The component ID.
   *
   * @return string|null
   *   The full path to the schema file, or NULL if not found.
   */
  public function getSchemaPath(string $component_id): ?string {
    return $this->getSchemaMap()[$component_id] ?? NULL;
  }

  /**
   * Builds and caches a map of component IDs to their schema file paths.
   *
   * @return array
   *   Array mapping component IDs to their schema file paths.
   */
  private function getSchemaMap(): array {
    if ($this->schemaMap !== NULL) {
      return $this->schemaMap;
    }

    if ($cache = $this->cache->get(self::CACHE_ID)) {
      $this->schemaMap = $cache->data;
      return $this->schemaMap;
    }

    $this->schemaMap = $this->buildSchemaMapping();
    
    // Cache permanently with schema tag for invalidation
    $this->cache->set(self::CACHE_ID, $this->schemaMap, CacheBackendInterface::CACHE_PERMANENT, ['xb_ai_component_schemas']);
    
    $this->loggerFactory->get('xb_ai')->info('Built and cached schema mapping for @count components', ['@count' => count($this->schemaMap)]);
    
    return $this->schemaMap;
  }

  /**
   * Builds the mapping of component IDs to schema file paths.
   *
   * @return array
   *   Array mapping component IDs to their schema file paths.
   */
  private function buildSchemaMapping(): array {
    $map = [];
    $schema_directory = DRUPAL_ROOT . self::SCHEMA_DIRECTORY;

    if (!is_dir($schema_directory)) {
      $this->loggerFactory->get('xb_ai')->error('Schema directory not found: @directory', ['@directory' => $schema_directory]);
      return [];
    }

    try {
      $files = scandir($schema_directory);
      
      foreach ($files as $file) {
        // Skip non-JSON files and special directories
        if (pathinfo($file, PATHINFO_EXTENSION) !== 'json' || strpos($file, '.') === 0) {
          continue;
        }

        // Extract component ID from filename (remove .json extension)
        $component_id = pathinfo($file, PATHINFO_FILENAME);
        
        // Handle special schema files that have .schema.json suffix
        if (str_ends_with($component_id, '.schema')) {
          $component_id = str_replace('.schema', '', $component_id);
        }

        $full_path = $schema_directory . $file;
        $map[$component_id] = $full_path;
      }

      $this->loggerFactory->get('xb_ai')->info('Discovered @count component schema files in @directory', [
        '@count' => count($map),
        '@directory' => $schema_directory,
      ]);

    } catch (\Exception $e) {
      $this->loggerFactory->get('xb_ai')->error('Error scanning schema directory: @error', ['@error' => $e->getMessage()]);
      return [];
    }

    return $map;
  }

  /**
   * Invalidates the schema cache.
   *
   * This method should be called when schema files are updated.
   */
  public function invalidateCache(): void {
    $this->cache->invalidate(self::CACHE_ID);
    $this->schemaMap = NULL;
    
    $this->loggerFactory->get('xb_ai')->info('Component schema cache invalidated');
  }

  /**
   * Gets all available component IDs.
   *
   * @return array
   *   Array of all available component IDs.
   */
  public function getAvailableComponentIds(): array {
    return array_keys($this->getSchemaMap());
  }

  /**
   * Checks if a component schema exists.
   *
   * @param string $component_id
   *   The component ID to check.
   *
   * @return bool
   *   TRUE if the schema exists, FALSE otherwise.
   */
  public function hasSchema(string $component_id): bool {
    return isset($this->getSchemaMap()[$component_id]);
  }

}
