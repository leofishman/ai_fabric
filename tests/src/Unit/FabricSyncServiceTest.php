<?php

declare(strict_types=1);

namespace Drupal\Tests\ai_fabric\Unit;

use Drupal\ai_fabric\FabricSyncService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\ai_fabric\FabricSyncService
 * @group ai_fabric
 */
final class FabricSyncServiceTest extends UnitTestCase {

  /**
   * Mocked EntityTypeManager.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  private $entityTypeManager;

  /**
   * Mocked Entity Storage.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  private $patternStorage;

  /**
   * Mocked FileSystem.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  private $fileSystem;

  /**
   * Mocked LoggerChannelFactory.
   *
   * @var \PHPUnit\Framework\MockObject\MockObject
   */
  private $loggerFactory;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->patternStorage = $this->createMock(EntityStorageInterface::class);
    $this->fileSystem = $this->createMock(FileSystemInterface::class);
    $this->loggerFactory = $this->createMock(LoggerChannelFactoryInterface::class);

    $logger = $this->createMock(LoggerChannelInterface::class);
    $this->loggerFactory->method('get')->willReturn($logger);

    $this->entityTypeManager->method('getStorage')
      ->with('fabric_pattern')
      ->willReturn($this->patternStorage);
  }

  /**
   * Tests that syncPatterns throws exception on invalid path.
   *
   * @covers ::syncPatterns
   */
  public function testSyncPatternsThrowsExceptionOnInvalidPath(): void {
    $sync_service = new FabricSyncService(
      $this->entityTypeManager,
      $this->fileSystem,
      $this->loggerFactory
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid directory');
    $sync_service->syncPatterns('/non_existent_directory_12345');
  }

  /**
   * Tests that exportPatterns throws exception on invalid path.
   *
   * @covers ::exportPatterns
   */
  public function testExportPatternsThrowsExceptionOnInvalidPath(): void {
    $sync_service = new FabricSyncService(
      $this->entityTypeManager,
      $this->fileSystem,
      $this->loggerFactory
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('is not a valid directory');
    $sync_service->exportPatterns('/non_existent_directory_12345');
  }

}

