<?php

declare(strict_types=1);

namespace Drupal\Tests\experience_builder\Functional;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Drupal\experience_builder\AutoSave\AutoSaveManager;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\Tests\ApiRequestTrait;
use Drupal\Tests\experience_builder\TestSite\XBTestSetup;
use Drupal\Tests\experience_builder\Traits\XBFieldTrait;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tests cacheability of ApiAutoSaveController.
 *
 * We cannot test this in a kernel test because the cache request policy
 * prevents caching as the request is seen as coming from the command line.
 *
 * @see \Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod
 * @coversDefaultClass \Drupal\experience_builder\Controller\ApiAutoSaveController
 * @group experience_builder
 */
final class ApiAutoSaveControllerCacheabilityTest extends FunctionalTestBase {

  use ApiRequestTrait;
  use XBFieldTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_page_cache',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    (new XBTestSetup())->setup();
    $this->setUpImages();
  }

  /**
   * {@inheritdoc}
   */
  public function testCaching(): void {
    $account1 = $this->createUser([
      'edit any article content',
    ]);
    self::assertInstanceOf(AccountInterface::class, $account1);
    $this->drupalLogin($account1);
    /** @var \Drupal\experience_builder\AutoSave\AutoSaveManager $autoSave */
    $autoSave = \Drupal::service(AutoSaveManager::class);
    $node1 = Node::load(1);
    \assert($node1 instanceof NodeInterface);
    $node1->setTitle($this->randomMachineName());
    $autoSave->saveEntity($node1);
    $url = Url::fromRoute('experience_builder.api.auto-save.get');
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $content = \json_decode($this->getSession()->getPage()->getContent() ?: '{}', TRUE);
    self::assertEquals([
      'node:1:en',
    ], \array_keys($content));

    // Second request should come from DPC.
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'HIT');

    // Make another post to preview controller, this should invalidate the
    // cache.
    $node2 = Node::load(2);
    \assert($node2 instanceof NodeInterface);
    $token = $this->drupalGet('session/token');

    $response = $this->makeApiRequest(
      'POST',
      Url::fromRoute('experience_builder.api.layout.post', [
        'entity_type' => 'node',
        'entity' => $node2->id(),
      ]),
      [
        RequestOptions::JSON => $this->getValidClientJson($node2, FALSE),
        RequestOptions::HEADERS => ['X-CSRF-Token' => $token],
      ]
    );
    self::assertEquals(Response::HTTP_OK, $response->getStatusCode());

    // Now the cache should be invalidated and we should get a MISS.
    $this->drupalGet($url);
    $this->assertSession()->responseHeaderEquals(DynamicPageCacheSubscriber::HEADER, 'MISS');
    $content = \json_decode($this->getSession()->getPage()->getContent() ?: '{}', TRUE);
    self::assertEquals([
      'node:1:en',
      'node:2:en',
    ], \array_keys($content));
  }

}
