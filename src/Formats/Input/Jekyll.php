<?php
namespace MarHue\Migrations\JekyllKirby\Formats\Input;

use MarHue\CMSMigrations\MigrationTool\Formats\BaseFormat;
use Illuminate\Filesystem\FilesystemAdapter;
use MarHue\CMSMigrations\MigrationTool\Formats\InputTrait;

class Jekyll extends BaseFormat
{
    use InputTrait;

    /**
     * The ID of this format.
     * @var string
     */
    protected $formatID = 'Jekyll';

    function __construct(FilesystemAdapter $inD)
    {
        $this->setDisk($inD);
    }//end construct

    /**
     * Returns all Directories within the Input folder
     * @return array
     */
    public function getAllInputFiles()
    {
        return $this->sortFiles($this->getDisk()->allFiles());
    } // function

    /**
     * sorts Files in matter of if they need to be migrated or just simply copied
     * @param array $files The file array.
     * @return array
     */
    protected function sortFiles(array $files)
    {
        $returnedFiles = array_filter($files, function($filePath) {
            return strpos(basename($filePath), '.') !== 0;
        });
        return $returnedFiles;
    } // function
}