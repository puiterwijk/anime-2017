<?php
// Copyright 2016 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

namespace Anime\Services;

class ImportTeamServiceTest extends \PHPUnit_Framework_TestCase {
    // Verifies that the given configuration options will be reflected in the getters.
    public function testOptionGetters() {
        $service = new ImportTeamService([
            'destination'   => '/path/to/destination/file',
            'frequency'     => 42,
            'identifier'    => 'import-team-service',
            'source'        => 'https://data/source.csv'
        ]);

        $this->assertEquals(42, $service->getFrequencyMinutes());
        $this->assertEquals('import-team-service', $service->getIdentifier());
    }

    // Verifies that a generic set of information can successfully be imported by the service, as
    // well as the guaranteed alphabetical ordering by full name in the generated file.
    public function testBasicImportTest() {
        $result = $this->importFromData([
            ['John Doe', 'Volunteer', 'john@doe.co.uk', '+447000000000', '201 (Cool Hotel)', 'Visible'],
            ['Jane Doe', 'Staff', 'jane@doe.co.uk', '+448000000000', '202 (Cool Hotel)', 'Hidden']
        ]);

        $this->assertEquals([
            [
                'name'      => 'Jane Doe',
                'type'      => 'Staff',
                'email'     => 'jane@doe.co.uk',
                'telephone' => '+448000000000',
                'hotel'     => '202 (Cool Hotel)',
                'visible'   => false
            ],
            [
                'name'      => 'John Doe',
                'type'      => 'Volunteer',
                'email'     => 'john@doe.co.uk',
                'telephone' => '+447000000000',
                'hotel'     => '201 (Cool Hotel)',
                'visible'   => true
            ]
        ], $result);
    }

    // Verifies that an invalid value for 'type' will throw an exception.
    /** @expectedException \Exception */
    public function testTypeValidation() {
        $result = $this->importFromData([
            ['John Doe', 'FooType', 'john@doe.co.uk', '+447000000000', '201 (Cool Hotel)', 'Visible'],
        ]);
    }

    // Verifies that an invalid value for 'visible' will throw an exception.
    /** @expectedException \Exception */
    public function testVisibilityValidation() {
        $result = $this->importFromData([
            ['John Doe', 'Staff', 'john@doe.co.uk', '+447000000000', '201 (Cool Hotel)', 'FooVisibility'],
        ]);
    }

    // Writes |$data| in CSV form to a file, then creates an ImportTeamService instance to parse it,
    // executes the service and reads back the result data from the destination.
    private function importFromData($data) {
        $source = tempnam(sys_get_temp_dir(), 'anime_');
        $destination = tempnam(sys_get_temp_dir(), 'anime_');

        // Write the input |$data| to the |$source| file.
        {
            $input = fopen($source, 'w');
            fwrite($input, PHP_EOL);  // the first line will be ignored

            foreach ($data as $line)
                fputcsv($input, $line);

            fclose($input);
        }

        try {
            // Create and execute the service using |$source| as the input data.
            $service = new ImportTeamService([
                'destination'   => $destination,
                'frequency'     => 0,
                'identifier'    => 'import-team-service',
                'source'        => $source
            ]);

            if (!$service->execute())
                throw new \Exception('Unable to execute the ImportTeamService.');

            return json_decode(file_get_contents($destination), true /* associative */);

        } finally {
            unlink($destination);
            unlink($source);
        }
    }
}
