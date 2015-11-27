<?php
namespace MarHue\Migrations\JekyllKirby\Formats\Output;

use Illuminate\Filesystem\FilesystemAdapter;
use MarHue\CMSMigrations\MigrationTool\Formats\BaseFormat;

class Kirby extends BaseFormat
{
    /**
     * The closing tag for the kirby templates.
     * @var string
     */
    const TEMPLATE_TAG_CLOSE = ' ?>';

    /**
     * The opening tag for the kirby templates.
     * @var string
     */
    const TEMPLATE_TAG_OPEN = '<?php ';

    protected $allHeaderElements = [];
    /**
     * The ID of this format.
     * @var string
     */
    protected $formatID = 'Kirby';

    protected $needsOwnTemplate = false;
    /**
     * Standard mapping rules
     * @var array
     */
    protected $mappingRules = [

        'Jekyll' => [
            'mapping' => [
                'complex' => [
                    ' content ' => "eval('?>' . \$page->content()->get('text') . '<?php ')",
                    'site\.baseurl' => '$site->url() . "/"',
                    'page.title' => '$page->title()',
                    'page.contentId' => '$page->contentId()',

                    ' include ' => "snippet('",
                    '\.html' => "');",
                    'page\.' => '$page->',
                    'site\.' => '$site->',
                    '\s\s+' => ' '
                ],
                'simple' => [
                    '{{' => self::TEMPLATE_TAG_OPEN . 'echo ',
                    '{%' => self::TEMPLATE_TAG_OPEN,
                    '}}' => ';' . self::TEMPLATE_TAG_CLOSE,
                    '%}' => self::TEMPLATE_TAG_CLOSE,
                    'h1.' => '#',
                    'h2.' => '##',
                    'h3.' => '###',
                    'h4.' => '####',
                    'h5.' => '#####',
                    'h6.' => '######',
                ]
            ],
            'filesToCopy' => [
                '_'
            ],
            'filesToMigrate' => [
                'md',
                'html',
                'php',
                'textile'
            ],
        ]
    ];

    protected $filesToCheck = [];

    function __construct(BaseFormat $input, FilesystemAdapter $disk)
    {
        $this
            ->setDisk($disk)
            ->setOtherFormat($input);

        //set the mapping rules dependend on input CMS
        $this->mappingRules = $this->mappingRules[$this->getOtherFormat()->getFormatID()]; // TODO
    }//end construct

    /**
     * Migrates Input-templates to Output-format
     * @param $inputDisk
     * @param $outputDisk
     * @param $file
     */
    protected function migrateFile($filePath)
    {
        $content = $this->getOtherFormat()->getDisk()->get($filePath); // TODO

        //migrate file Header
        $content = $this->migrateFileHeader($content);

        array_walk(
            $this->mappingRules['mapping']['simple'],
            function($value, $key) use (&$content) {
                $content = str_replace($key, $value, $content);
            }
        );

        array_walk(
            $this->mappingRules['mapping']['complex'],
            function($value, $key) use (&$content) {
                $sep = '/';

                $content = preg_replace(
                    $sep .
                        '(' . preg_quote(self::TEMPLATE_TAG_OPEN, $sep) . '.*)' .
                        '(' . $key . ')' .
                        '(.*' . preg_quote(self::TEMPLATE_TAG_CLOSE, $sep) . ')' .
                    $sep,
                    '$1' . $value . '$3',
                    $content
                );
            }
        );

        return $this->saveFile($filePath, $content);
    }//function

    /**
     * Saves the migrated File to OutputFolder
     * @param $inputDisk
     * @param $outputDisk
     * @param $migratedFile
     * @param $content
     */
    protected function saveFile($filePath, $content)
    {

        if (substr($filePath, 0, 1) === '_') {
            //if filename begin with _includes, put file in folder site/snippet as .php file
            if (substr($filePath, 0, 9) === '_includes') {
                $this->getDisk()->put($newPath = 'site' . DIRECTORY_SEPARATOR . 'snippets' . DIRECTORY_SEPARATOR .
                    pathinfo($filePath, PATHINFO_FILENAME) . '.' . str_replace(pathinfo($filePath, PATHINFO_EXTENSION),
                        'php', pathinfo($filePath, PATHINFO_EXTENSION)), $content);
            }
            //if filename begin with _layouts, put file in folder site/templates as .php file
            if (substr($filePath, 0, 8) === '_layouts') {
                $this->getDisk()->put($newPath = 'site' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR .
                    pathinfo($filePath, PATHINFO_FILENAME) . '.' . str_replace(pathinfo($filePath, PATHINFO_EXTENSION),
                        'php', pathinfo($filePath, PATHINFO_EXTENSION)), $content);
            } else {
                //put remaining folders beginning with '_' in separate folders within content, every template with it's own
                //subfolder

                //if Contentfile needs its own layout, search for the needed template, remame content file
                if (strpos($content, 'layout:') !== false) {
                    preg_match('/(?<=layout:).[^\\s]+/', $content, $matches);
                    $whichTemplate = trim($matches[0]);
                    $this->getDisk()->put($newPath = 'content' . DIRECTORY_SEPARATOR . str_replace('_', '',
                            pathinfo($filePath, PATHINFO_DIRNAME)) . DIRECTORY_SEPARATOR .
                        pathinfo($filePath, PATHINFO_FILENAME) .'.html'. DIRECTORY_SEPARATOR . $whichTemplate . '.txt',
                        $content);
                    //else, put them as subfolder with their original name
                } else {
                    $this->getDisk()->put($newPath = 'content' . DIRECTORY_SEPARATOR . str_replace('_', '',
                            pathinfo($filePath, PATHINFO_DIRNAME)) . '.html'. DIRECTORY_SEPARATOR .
                        pathinfo($filePath, PATHINFO_FILENAME) . DIRECTORY_SEPARATOR . pathinfo($filePath,
                            PATHINFO_FILENAME) . '.txt', $content);
                }
            }
        } else {
            //if Contentfile needs its own layout, search for the needed template, remame content file and
            //create a copy as php file in templates folder
            if (strpos($content, 'layout:') !== false) {
                //look, which layout is chosen
                preg_match('/(?<=layout:).[^\\s]+/', $content, $matches);
                $whichTemplate = trim($matches[0]);

                $this->getDisk()->put($newPath = 'content' . DIRECTORY_SEPARATOR . pathinfo($filePath, PATHINFO_FILENAME) . '.html'.
                    DIRECTORY_SEPARATOR . str_replace(pathinfo($filePath, PATHINFO_FILENAME), $whichTemplate,
                        str_replace(pathinfo($filePath, PATHINFO_EXTENSION), 'txt', $filePath), $filePath),
                    $content);
            } else {
                //else, just save migrated file in its own subfolder in content folder as .txt
                $this->getDisk()->put($newPath = 'content' . DIRECTORY_SEPARATOR . pathinfo($filePath, PATHINFO_FILENAME) . '.html'.
                    DIRECTORY_SEPARATOR . str_replace(pathinfo($filePath, PATHINFO_EXTENSION), 'txt', $filePath),
                    $content);
            }
        }

        return preg_match('/' . self::TEMPLATE_TAG_OPEN. '.*(assign|if|for|split|append|first|upcase|endif).*'. self::TEMPLATE_TAG_CLOSE . '/', $content)
            ? $newPath
            : true;
    }//function

    /**
     * @param $filePath
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function copyFile($filePath)
    {
        //if the file is "_config.yml, copy file as .txt into content-rootfolder
        if ($filePath === 'config.yml') {
            $this->getDisk()->put('content' . DIRECTORY_SEPARATOR . 'site.txt',
                $this->getOtherFormat()->getDisk()->get($filePath));
        }  //else, copy them in /assets
        else {
            $this->getDisk()->put($filePath,
                $this->getOtherFormat()->getDisk()->get($filePath));
        }

    } // function

    /**
     * Changes the Content-File-Headers to Kirby-comfort syntax
     * @param $filePath
     * @param $content
     * @return mixed|string
     */
    protected function migrateFileHeader($content)
    {

        $oldHeader = $this->get_string_between($content, '---', '---');
        $dummy = explode(PHP_EOL, $oldHeader);
        foreach ($dummy as $value) {
            if (!in_array(preg_split('/(.*?):/', $value), $this->allHeaderElements) && (preg_split('/(.*?):/',
                        $value) !== null)
            ) {
                array_push($this->allHeaderElements, preg_split('/(.*?):/', $value));
            }
        }
        $newHeader = str_replace(PHP_EOL, PHP_EOL . '----' . PHP_EOL, $oldHeader);
        $newHeader = $newHeader . PHP_EOL . 'text: ';
        $content = str_replace('---' . $oldHeader . '---', $newHeader, $content);
        //delete the first wrong divider
        $content = preg_replace('/----/', '', $content, 1);
        //deletes whitespaces and line breaks
        $content = trim($content);
        return $content;
    }

    /**
     * @param $string
     * @param $start
     * @param $end
     * @return string
     */
    protected function get_string_between($string, $start, $end)
    {
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) {
            return '';
        }
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        $value = substr($string, $ini, $len);
//        foreach ($this->mappingRules['onlyHeaderMapping'] as $key => $value) {
//            $value = str_replace($key, $value, $value);
//        }

        return $value;
//        return substr($string, $ini, $len);
    }

    /**
     *
     */
    public function startMigration()
    {
        $allFiles = $this->getOtherFormat()->getAllInputFiles();
        $wrongFiles = [];

        foreach ($allFiles as $file) {

            if (in_array(pathinfo($file, PATHINFO_EXTENSION), $this->mappingRules['filesToMigrate']) && strpos($file,
                    'vendor') === false && strpos($file, 'assets') === false
            ) {
                $migrated = $this->migrateFile($file);

                if ($migrated !== true) {
                    $wrongFiles[] = [$migrated];
                }  // if
            } else {
                $this->copyFile($file);
            }
        }

        return $wrongFiles ?: true;
    }
}