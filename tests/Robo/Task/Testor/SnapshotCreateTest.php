<?php

namespace PL\Tests\Robo\Task\Testor {

    use PL\Robo\Task\Testor\SnapshotCreate;

    class SnapshotCreateTest extends TestorTestCase
    {

//        public function testInjected()
//        {
//            $this->assertSame($this->mockS3, $this->mockSnapshotCreate()->getS3Client());
//        }

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
            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => '', 'element' => 'database']);
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

        public function testSnapshotCreate()
        {
            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'database']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=database')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:list performant-labs.dev --format=json')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, '{"2": {"file": "11111_database.sql.gz"}, "1": {"file": "22222_database.sql.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=11111_database.sql.gz --to=11111_database.sql.gz')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            $snapshotCreate->setBuilder($mockBuilder);

            // Mock S3Client.
            $mockS3Client = $this->mockS3Client();
            $mockS3Client
                ->shouldReceive('putObject')
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/11111_database.sql.gz',
                    'SourceFile' => '11111_database.sql.gz'
                    ))
                ->andReturn(new \Aws\Result());
            $snapshotCreate->setS3Client($mockS3Client);
            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testSnapshotCreateFiles()
        {
            $mockBuilder = $this->mockCollectionBuilder();

            $snapshotCreate = $this->taskSnapshotCreate(['env' => 'dev', 'name' => 'test', 'element' => 'files']);
            // Command #1
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:create performant-labs.dev --element=files')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            // Command #2
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:list performant-labs.dev --format=json')
                ->andReturn($this->mockTaskExec($snapshotCreate, 0, '{"2": {"file": "11111_files.sql.gz"}, "1": {"file": "22222_files.sql.gz"}}'));
            // Command #3
            $mockBuilder
                ->shouldReceive('taskExec')
                ->once()
                ->with('terminus backup:get performant-labs.dev --file=11111_files.sql.gz --to=11111_files.sql.gz')
                ->andReturn($this->mockTaskExec(new \Robo\Result($snapshotCreate, 0, 'OK')));
            $snapshotCreate->setBuilder($mockBuilder);

            // Mock S3Client.
            $mockS3Client = $this->mockS3Client();
            $mockS3Client
                ->shouldReceive('putObject')
                ->with(array(
                    'Bucket' => 'snapshot',
                    'Key' => 'test/11111_files.sql.gz',
                    'SourceFile' => '11111_files.sql.gz'
                ))
                ->andReturn(new \Aws\Result());
            $snapshotCreate->setS3Client($mockS3Client);
            $result = $snapshotCreate->run();
            $this->assertEquals(0, $result->getExitCode());
        }

        public function testTerminusNotFound()
        {
//            TODO
        }

        public function testTerminusError()
        {
//            TODO
        }
    }
}
