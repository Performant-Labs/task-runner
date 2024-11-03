<?php

namespace PL\Tests\Robo\Task\Testor {

    use PL\Robo\Common\StorageS3;
    use PL\Robo\Task\Testor\SnapshotCreate;
    use PL\Robo\Task\Testor\SnapshotPut;

    class SnapshotCreateTest extends TestorTestCase
    {
        public function tearDown(): void
        {
            parent::tearDown();
            if (file_exists('__test_snapshot_create.sql')) {
                unlink('__test_snapshot_create.sql');
            }
            if (file_exists('__test_snapshot_create.tar')) {
                unlink('__test_snapshot_create.tar');
            }
            if (file_exists('__test_snapshot_create.tar.gz')) {
                unlink('__test_snapshot_create.tar.gz');
            }
        }

        /**
         * @param $command
         * @dataProvider providerCommand
         * @return void
         */
        public function testExec($command)
        {
            // Test that exec() method actually executes command
            // and returns its return code and output.
            /** @var SnapshotCreate $snapshotCreate */
            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '', 'element' => 'database', 'filename' => '__test_snapshot_create', 'ispantheon' => true]);
            $result = $snapshotCreate->exec($command, $output);

            // Reference result through built-in exec.
            \exec($command, $lines, $code);
            // We can get text result either as $output, or as $result->getMessage().
            // $ouptut work in the actual task but doesn't work here.
            // I have no idea why.
            $this->assertEquals(implode("\n", $lines), $result->getMessage());
            $this->assertEquals($code, $result->getExitCode());
        }

        /**
         * @dataProvider
         */
        public static function providerCommand(): array
        {
            return [
                // this should work both on Linux and Windows
                ['hostname'],
                // exit with error and print usage
                ['hostname --malformed'],
                // non-existing command
                ['non-existing-command'],
            ];
        }

        public function testSnapshotCreateRemotely()
        {
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            // We don't mock PharData, so have to create real files.
            file_put_contents('__test_snapshot_create.sql', 'select 1+1;');
            $opts = ['env' => 'dev', 'name' => 'test', 'element' => 'database', 'filename' => '__test_snapshot_create'];
            $snapshotCreate = $this->taskSnapshotCreate([...$opts, 'ispantheon' => true, 'gzip' => false]);
            $snapshotImport = $this->taskSnapshotImport([...$opts, 'gzip' => false]);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus remote:drush performant-labs.dev -- sql:dump > __test_snapshot_create.sql')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('$(drush sql:connect) < __test_snapshot_create.sql')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, 'OK'));
            $snapshotCreate->setBuilder($mockBuilder);
            $snapshotImport->setBuilder($mockBuilder);

            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());

            $result = $snapshotImport->run();
            $this->assertEquals(0, $result->getExitCode());

            // Check that the sql file isn't corrupted during gzip-gunzip.
            self::assertEquals('select 1+1;', file_get_contents('__test_snapshot_create.sql'));
        }

        function testSnapshotCreateLocally()
        {
            $mockBuilder = $this->mockCollectionBuilder();
            file_put_contents('__test_snapshot_create.sql', 'select 1+1;');
            $snapshotCreate = $this->taskSnapshotCreate(['env' => '@self', 'name' => 'test', 'element' => 'database', 'filename' => '__test_snapshot_create', 'ispantheon' => false]);
            $mockBuilder->shouldReceive('taskExec')
                ->once()
                ->with('drush sql:dump > __test_snapshot_create.sql')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, 'OK'));
            $snapshotCreate->setBuilder($mockBuilder);

            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());

            // Unpack .tar.gz and check content.
            \exec('tar -xf __test_snapshot_create.tar.gz');
            $this->assertEquals('select 1+1;', file_get_contents('__test_snapshot_create.sql'));
        }

        public function testSnapshotCreateViaBackup()
        {
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotViaBackup = $this->taskSnapshotViaBackup(['env' => 'dev', 'name' => 'test', 'element' => 'files', 'filename' => '__test_snapshot_create']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=files --keep-for=1')
                ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:list performant-labs.dev --format=json')
                ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, '{"2": {"file": "performant-labs_11111_files.tar.gz"}, "1": {"file": "performant-labs_22222_files.tar.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=performant-labs_11111_files.tar.gz --to=__test_snapshot_create.tar.gz')
                ->andReturn($this->mockTaskExec($snapshotViaBackup, 0, 'OK'));
            $snapshotViaBackup->setBuilder($mockBuilder);

            $result = $snapshotViaBackup->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testDbSanitizeNoCommand()
        {
            $mockBuilder = $this->mockCollectionBuilder();
            $dbSanitize = $this->taskDbSanitize(['do-not-sanitize' => false]);
            $mockBuilder->shouldReceive('taskExec')
                ->never();
            $dbSanitize->setBuilder($mockBuilder);

            $result = $dbSanitize->run();
            $this->assertEquals(0, $result->getExitCode());
            $this->assertStringContainsString('Skip', $result->getMessage());
        }

        public function testDbSanitizeDoNotSanitize()
        {
            /** @var \Consolidation\Config\Config $testorConfig */
            $testorConfig = $this->getContainer()->get('testorConfig');
            $testorConfig->set('sanitize.command', 'drush sql:sanitize');
            $mockBuilder = $this->mockCollectionBuilder();
            $dbSanitize = $this->taskDbSanitize(['do-not-sanitize' => true]);
            $mockBuilder->shouldReceive('taskExec')
                ->never();
            $dbSanitize->setBuilder($mockBuilder);

            $result = $dbSanitize->run();
            $this->assertEquals(0, $result->getExitCode());
            $this->assertStringContainsString('Skip', $result->getMessage());
        }

        public function testDbSanitize()
        {
            /** @var \Consolidation\Config\Config $testorConfig */
            $testorConfig = $this->getContainer()->get('testorConfig');
            $testorConfig->set('sanitize.command', 'drush sql:sanitize');
            $mockBuilder = $this->mockCollectionBuilder();
            $dbSanitize = $this->taskDbSanitize(['do-not-sanitize' => false]);
            $mockBuilder->shouldReceive('taskExec')
                ->once()
                ->with('drush sql:sanitize')
                ->andReturn($this->mockTaskExec($dbSanitize, 0, 'OK'));
            $dbSanitize->setBuilder($mockBuilder);

            $result = $dbSanitize->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testSnapshotPut()
        {
            $snapshotPut = $this->taskSnapshotPut(['name' => 'test', 'filename' => '__test_snapshot_create']);

            // Mock S3Client.
            $this->mockS3Client
                ->shouldReceive('putObject')
                ->once()
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/__test_snapshot_create.tar.gz',
                    'SourceFile' => '__test_snapshot_create.tar.gz'
                ))
                ->andReturn(new \Aws\Result());

            $result = $snapshotPut->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testTerminusNotFound()
        {
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('');

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '', 'element' => 'database', 'filename' => '__test_snapshot_create', 'ispantheon' => true]);
            $result = $snapshotCreate->run();
            $this->assertEquals(1, $result->getExitCode());
            $this->assertStringContainsString('Please install and configure terminus', $result->getMessage());
        }

        public function testTerminusError()
        {
            // Mock shell_exec (for `isExecutable`)
            $mockShellExec = $this->mockBuiltIn('shell_exec');
            $mockShellExec->expects(self::once())
                ->with('which terminus')
                ->willReturn('/usr/bin/terminus');

            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'database', 'filename' => '__test_snapshot_create', 'ispantheon' => true]);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus remote:drush performant-labs.dev -- sql:dump > __test_snapshot_create.sql')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 1, 'SPOOKY SCARY ERROR')));
            $snapshotCreate->setBuilder($mockBuilder);

            $result = $snapshotCreate->run();
            $this->assertEquals(1, $result->getExitCode());
            $this->assertStringContainsString('SPOOKY SCARY ERROR', $result->getMessage());
        }
    }
}
