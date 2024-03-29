<?php

namespace Drupal\Test\Commenting;

use Drupal\Test\CoderSniffUnitTest;

class DocCommentUnitTest extends CoderSniffUnitTest
{


    /**
     * Returns the lines where errors should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of errors that should occur on that line.
     *
     * @param string $testFile The name of the file being tested.
     *
     * @return array<int, int>
     */
    protected function getErrorList(string $testFile): array
    {
        switch ($testFile) {
        case 'DocCommentUnitTest.inc':
            return [
                8   => 1,
                12  => 1,
                14  => 1,
                16  => 1,
                29  => 1,
                36  => 2,
                45  => 1,
                66  => 1,
                100 => 4,
                101 => 1,
                136 => 1,
                141 => 1,
            ];

        case 'DocCommentUnitTest.1.inc':
            return [
                24 => 1,
                28 => 1,
                32 => 1,
                36 => 1,
                41 => 2,
            ];

        case 'DocCommentUnitTest.3.inc':
            return [4 => 1];
        default:
            return [];
        }//end switch

    }//end getErrorList()


    /**
     * Returns the lines where warnings should occur.
     *
     * The key of the array should represent the line number and the value
     * should represent the number of warnings that should occur on that line.
     *
     * @param string $testFile The name of the file being tested.
     *
     * @return array<int, int>
     */
    protected function getWarningList(string $testFile): array
    {
        return [];

    }//end getWarningList()


}//end class
