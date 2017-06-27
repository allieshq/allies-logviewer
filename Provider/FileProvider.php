<?php

namespace Allies\Bundle\LogViewerBundle\Provider;

class FileProvider
{
    
/******************************************************************************
 * PROPERTIES
 ******************************************************************************/
    
    /**
     * @var AppKernel
     */
    protected $kernel;
    
/******************************************************************************
 * MAGIC
 ******************************************************************************/
    
    /**
     * @param \AppKernel $kernel
     */
    public function __construct(\AppKernel $kernel)
    {
        $this->kernel = $kernel;
    }
    
/******************************************************************************
 * METHODS
 ******************************************************************************/
    
    /**
     * @return array
     */
    public function getLogFilesSummary()
    {
        $return = [];
        
        $di = new \DirectoryIterator($this->kernel->getLogDir());
        foreach ($di as $fileInfo) {
            if (!$fileInfo->isDot() && !$fileInfo->isDir() && 'log' == $fileInfo->getExtension()) {
                $return[] = [
                    'filename' => $fileInfo->getFilename(),
                    'mtime' => $fileInfo->getMTime(),
                    'mtime_readable' => date('Y-m-d H:i:s', $fileInfo->getMTime()),
                    'size' => $fileInfo->getSize(),
                    'size_readable' => $this->convertFileSize($fileInfo->getSize()),
                    'readable' => $fileInfo->isReadable(),
                    'lines' => $this->getFileLineCount($fileInfo->getFilename()),
                ];
            }
        }
        
        return $return;
    }
    
    /**
     * @link https://stackoverflow.com/a/2162528
     * 
     * @param string $filename
     * @throws \RuntimeException
     */
    public function getFileLineCount($filename)
    {
        $file = $this->getLogFilepath($filename);
        if (!$file) {
            throw new \RuntimeException(sprintf(
                    "Cannot find file %s",
                    $filename
                ));
        }
        
        $lineCount = 0;
        $handle = fopen($file, "r");
        while(!feof($handle)){
            $line = fgets($handle);
            $lineCount++;
        }

        fclose($handle);
        
        return $lineCount;
    }
    
    /**
     * 
     * @param string $filename
     * @param integer $start
     * @param integer $lines
     * @return array
     */
    public function readFileParsed($filename, $start=-30, $lines=30)
    {
        return $this->parseReadArray($this->readFile($filename, $start, $lines));
    }
    
    /**
     * @param array $readArray
     * @return array
     */
    public function parseReadArray(array $readArray)
    {
        $return = [];
        foreach ($readArray as $lineNo => $line) {
            $matches = [];
            preg_match_all("#^\s*\[?(\d{4}-\d{2}-\d{2}(T| )\d{2}:\d{2}:\d{2}(\+\d{2}:\d{2})?)\]?\s+([^:]+):\s*(.*)$#", $line, $matches);
            if (isset($matches[1][0]) && isset($matches[4][0]) && isset($matches[5][0])) {
                $return[] = [
                    'lineNo' => $lineNo+1,
                    'datetime' => $matches[1][0],
                    'type' => $matches[4][0],
                    'message' => $matches[5][0],
                ];
            } else {
                $return[] = [
                    'lineNo' => $lineNo+1,
                    'datetime' => null,
                    'type' => null,
                    'message' => $line,
                ];
            }
        }
        
        return $return;
    }
    
    /**
     * @param string $filename
     * @param integer $start
     * @param integer $lines
     * @return array
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function readFile($filename, $start=-30, $lines=30)
    {
        $start = (int)$start;
        $lines = (int)$lines;
        
        if ($lines <= 0) {
            throw new \InvalidArgumentException(sprintf(
                    "Total lines to read must be greater than zero. '%s' passed",
                    $lines
                ));
        }
        
        $file = $this->getLogFilepath($filename);
        if (!$file) {
            throw new \RuntimeException(sprintf(
                    "Cannot find file %s",
                    $filename
                ));
        }
        
        $fileLineCount = $this->getFileLineCount($file);
        
        if ($start < 0) {
            $start = max(0, $fileLineCount+$start);
        }
        
        $handle = fopen($file, "r");
        $curLineNo = 0;
        $lineCount = 0;
        $end = false;
        $return = [];
        
        while (!feof($handle) && $lineCount < $lines){
            $line = fgets($handle);
            if ($curLineNo >= $start) {
                $return[$curLineNo] = $line;
                $lineCount++;
            }
            $curLineNo++;
        }
        
        fclose($handle);
        
        return $return;
    }
    
/******************************************************************************
 * INTERNAL
 ******************************************************************************/
    
    /**
     * @param string $filename
     * @return string|false
     */
    protected function getLogFilepath($filename)
    {
        $logDir = $this->kernel->getLogDir();
        $filename = str_replace($logDir, null, $filename);
        $filename = ltrim($filename, './\\');
        
        return realpath($logDir.DIRECTORY_SEPARATOR.$filename);
    }
    
    /**
     * @param integer $fileSize
     * @return string
     */
    protected function convertFileSize($fileSize)
    {
        $fileSize = (int)$fileSize;
        switch (true) {
            case $fileSize >= (1024 * 1024 * 1024) :
                $humanFs = $fileSize / (1024*1024*1024);
                $unit = 'GB';
                break;
            case $fileSize >= (1024 * 1024) :
                $humanFs = $fileSize / (1024*1024);
                $unit = 'MB';
                break;
            case $fileSize >= (1024) :
                $humanFs = $fileSize / (1024);
                $unit = 'KB';
                break;
            default :
                $humanFs = $fileSize;
                $unit = '';
                break;
        }
        
        return sprintf("%s %s", number_format($humanFs, 2), $unit);
    }
    
}