<?php
/**
 * Copyright (C) 2015  Gerrit Addiks.
 * This package (including this file) was released under the terms of the GPL-3.0.
 * You should have received a copy of the GNU General Public License along with this program.
 * If not, see <http://www.gnu.org/licenses/> or send me a mail so i can send you a copy.
 * @license GPL-3.0
 * @author Gerrit Addiks <gerrit@addiks.de>
 */

namespace Addiks\PHPSQL\Index;

use Addiks\PHPSQL\Filesystem\FilesystemInterface;
use Addiks\PHPSQL\Index\IndexSchema;
use Addiks\PHPSQL\Table\TableSchemaInterface;
use Addiks\PHPSQL\Filesystem\FilePathes;
use Addiks\PHPSQL\Index\IndexInterface;
use Addiks\PHPSQL\Value\Enum\Page\Index\IndexEngine;

class IndexFactory implements IndexFactoryInterface
{

    public function __construct(FilesystemInterface $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    protected $filesystem;

    protected $indexFactories = array();

    public function getIndexFactories()
    {
        return $this->indexFactories;
    }

    public function getIndexFactory(IndexEngine $engine)
    {
        $indexFactory = null;

        if (isset($this->indexFactories[(string)$engine])) {
            $indexFactory = $this->indexFactories[(string)$engine];
        }

        return $indexFactory;
    }

    public function hasIndexFactory(IndexEngine $engine)
    {
        return isset($this->indexFactories[(string)$engine]);
    }

    public function registerIndexFactory(IndexEngine $engine, IndexFactoryInterface $indexFactory)
    {
        $this->indexFactories[(string)$engine] = $indexFactory;
    }

    public function unregisterIndexFactory(IndexEngine $engine)
    {
        unset($this->indexFactories[(string)$engine]);
    }
    
    public function createIndex(
        $schemaId,
        $tableId,
        $indexId,
        TableSchemaInterface $tableSchema,
        IndexSchema $indexSchema
    ) {
        
        $indexDataFilepath = sprintf(
            FilePathes::FILEPATH_INDEX_DATA,
            $schemaId,
            $tableId,
            $indexId
        );

        $indexDataFile = $this->filesystem->getFile($indexDataFilepath);

        $filePath = sprintf(
            FilePathes::FILEPATH_TABLE_COLUMN_INDEX,
            $schemaId,
            $tableId,
            $indexId
        );

        $indexDoublesFilepath = "";
        $indexDoublesFile = null;
        if (!$indexSchema->isUnique()) {
            $indexDoublesFilepath = sprintf(
                FilePathes::FILEPATH_INDEX_DOUBLES,
                $schemaId,
                $tableId,
                $indexId
            );

            $indexDoublesFile = $this->filesystem->getFile($indexDoublesFilepath);
        }

        $engine = $indexSchema->getEngine();

        /* @var $index IndexInterface */
        $index = null;

        if ($this->hasIndexFactory($engine)) {
            /* @var $indexFactory IndexFactoryInterface */
            $indexFactory = $this->getIndexFactory($engine);

            $index = $indexFactory->createIndex(
                $schemaId,
                $tableId,
                $indexId,
                $tableSchema,
                $indexSchema
            );

        } else {
            switch($engine){
                
                default:
                case IndexEngine::RTREE():
                    $engineName = "UNKNOWN";
                    if (is_object($indexSchema->getEngine())) {
                        $engineName = $indexSchema->getEngine()->getName();
                    }
                    trigger_error(
                        "Requested unimplemented INDEX-ENGINE {$engineName}, using B-TREE instead!",
                        E_USER_NOTICE
                    );
                    # Use B-TREE instead of unimplemented index-engine
                
                case IndexEngine::BTREE():
                    $index = new BTree($indexDataFile, $tableSchema, $indexSchema);
                    
                    if (!$indexSchema->isUnique()) {
                        $index->setDoublesFile($indexDoublesFile);
                    }
                    break;
                    
                case IndexEngine::HASH():
                    $index = new HashTable($indexDataFile, $indexSchema->getKeyLength());
                        
                    if (!$indexSchema->isUnique()) {
                        $index->setDoublesFile($indexDoublesFile);
                    }
                    break;
            }
        }

        return $index;
    }

}
