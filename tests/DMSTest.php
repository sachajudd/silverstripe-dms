<?php
class DMSTest extends FunctionalTest
{
    protected $usesDatabase = true;

    /**
     * Stub PDF files for testing
     * @var string
     */
    public static $testFile = 'dms/tests/DMS-test-lorum-file.pdf';
    public static $testFile2 = 'dms/tests/DMS-test-document-2.pdf';

    /**
     * Store values to reset back to after this test runs
     */
    public static $dmsFolderOld;
    public static $dmsFolderSizeOld;

    public function setUp()
    {
        parent::setUp();

        self::$dmsFolderOld = DMS::$dmsFolder;
        self::$dmsFolderSizeOld = DMS::$dmsFolderSize;

        //use a test DMS folder, so we don't overwrite the live one
        DMS::$dmsFolder = 'dms-assets-test-1234';

        //clear out the test folder (in case a broken test doesn't delete it)
        $this->delete(BASE_PATH . DIRECTORY_SEPARATOR . 'dms-assets-test-1234');
    }

    public function tearDown()
    {
        parent::tearDown();

        self::$is_running_test = true;

        $d = DataObject::get("DMSDocument");
        foreach ($d as $d1) {
            $d1->delete();
        }
        $t = DataObject::get("DMSTag");
        foreach ($t as $t1) {
            $t1->delete();
        }

        //delete the test folder after the test runs
        $this->delete(BASE_PATH . DIRECTORY_SEPARATOR . 'dms-assets-test-1234');

        //set the old DMS folder back again
        DMS::$dmsFolder = self::$dmsFolderOld;
        DMS::$dmsFolderSize = self::$dmsFolderSizeOld;

        self::$is_running_test = $this->originalIsRunningTest;
    }

    /**
     * Delete a file that was created during a unit test
     *
     * @param string $path
     */
    public function delete($path)
    {
        if (file_exists($path) || is_dir($path)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                if (in_array($file->getBasename(), array('.', '..'))) {
                    continue;
                } elseif ($file->isDir()) {
                    rmdir($file->getPathname());
                } elseif ($file->isFile() || $file->isLink()) {
                    unlink($file->getPathname());
                }
            }
            rmdir($path);
        }
    }

    public function testDMSStorage()
    {
        $dms = DMS::inst();

        $file = self::$testFile;
        $document = $dms->storeDocument($file);

        $this->assertNotNull($document, "Document object created");
        $this->assertTrue(
            file_exists(
                DMS::get_dms_path() . DIRECTORY_SEPARATOR . $document->Folder
                . DIRECTORY_SEPARATOR . $document->Filename
            ),
            "Document file copied into DMS folder"
        );
    }

    public function testDMSFolderSpanning()
    {
        DMS::$dmsFolderSize = 5;
        $dms = DMS::inst();

        $file = self::$testFile;

        $documents = array();
        for ($i = 0; $i <= 16; $i++) {
            $document = $dms->storeDocument($file);
            $this->assertNotNull($document, "Document object created on run number: $i");
            $this->assertTrue(file_exists($document->getFullPath()));
            $documents[] = $document;
        }

        // Test document objects have their folders set
        $folders = array();
        for ($i = 0; $i <= 16; $i++) {
            $folderName = $documents[$i]->Folder;
            $this->assertTrue(
                strpos($documents[$i]->getFullPath(), DIRECTORY_SEPARATOR . $folderName . DIRECTORY_SEPARATOR) !== false,
                "Correct folder name for the documents. Document path contains reference to folder name '$folderName'"
            );
            $folders[] = $folderName;
        }

        // Test we created 4 folder to contain the 17 files
        foreach ($folders as $f) {
            $this->assertTrue(is_dir(DMS::get_dms_path() . DIRECTORY_SEPARATOR . $f), "Document folder '$f' exists");
        }
    }

    public function testReplaceDocument()
    {
        $dms = DMS::inst();

        // Store the first document
        $document = $dms->storeDocument(self::$testFile);
        $document->Title = "My custom title";
        $document->Description = "My custom description";
        $document->write();

        // Then overwrite with a second document
        $document = $document->replaceDocument(self::$testFile2);

        $this->assertNotNull($document, "Document object created");
        $this->assertTrue(
            file_exists(
                DMS::get_dms_path() . DIRECTORY_SEPARATOR . $document->Folder
                . DIRECTORY_SEPARATOR . $document->Filename
            ),
            "Document file copied into DMS folder"
        );
        $this->assertContains(
            "DMS-test-document-2",
            $document->Filename,
            "Original document filename is contain in the new filename"
        );
        $this->assertEquals("My custom title", $document->Title, "Custom title not modified");
        $this->assertEquals("My custom description", $document->Description, "Custom description not modified");
    }
}
