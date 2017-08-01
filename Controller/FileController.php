<?php

namespace Allies\Bundle\LogViewerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\SecurityBundle\Annotation\Acl;
use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;
use Oro\Bundle\SoapBundle\Entity\Manager\ApiEntityManager;

/**
 * @Route("/file")
 */
class FileController extends Controller
{
    
/******************************************************************************
 * Routes
 ******************************************************************************/
    
    /**
     * @Route(
     *      "/{_format}",
     *      name="allies_logviewer_file_index",
     *      requirements={"_format"="html|json"},
     *      defaults={"_format"="html"}
     * )
     * @AclAncestor("allies_logviewer_file_view")
     * @Template()
     */
    public function indexAction()
    {
        $provider = $this->container->get('allies.logviewer.provider.file');
        
        return [
            'file_summary' => $provider->getLogFilesSummary(),
        ];
    }
    
    /**
     * @Route(
     *      "/read/{filename}/{start}/{lines}",
     *      name="allies_logviewer_file_view",
     *      requirements={
     *          "filename"="^[a-zA-Z0-9][a-zA-Z0-9_\-]+\.log$", 
     *          "start"="^\-?[0-9]+$", 
     *          "lines"="^[0-9]+$"
     *      },
     *      defaults={"start"="-30", "lines"="30"}
     * )
     * @Template()
     */
    public function viewAction($filename, $start=-30, $lines=30)
    {
        $flashBag = $this->get('session')->getFlashBag();
        $provider = $this->container->get('allies.logviewer.provider.file');
        
        try {
            $output = $provider->readFileParsed($filename, $start, $lines);
        } catch (\Exception $e) {
            $flashBag->add('error', $e->getMessage());
            $output = [];
        }
        
        return [
            'filename' => $filename,
            'lines' => $lines, 
            'output' => $output,
        ];
    }
    
    /**
     * @Route(
     *      "/grep/{filename}/{pattern}/{start}/{lines}/{caseSensitive}",
     *      name="allies_logviewer_file_grep",
     *      requirements={
     *          "filename"="^[a-zA-Z0-9][a-zA-Z0-9_\-]+\.log$", 
     *          "pattern"="[^/]+",
     *          "start"="^[0-9]+$", 
     *          "lines"="^[0-9]+$",
     *          "caseSensitive"="^(0|1)?$"
     *      },
     *      defaults={"start"="0", "lines"="30", "caseSensitive"=0}
     * )
     * @AclAncestor("allies_logviewer_file_view")
     * @Template()
     */
    public function grepAction($filename, $pattern, $start=0, $lines=30, $caseSensitive=0)
    {
        $caseSensitive = (bool)(int)$caseSensitive;
        
        $flashBag = $this->get('session')->getFlashBag();
        $provider = $this->container->get('allies.logviewer.provider.file');
        
        try {
            $output = $provider->grepFileParsed($filename, $pattern, $start, $lines, $caseSensitive);
        } catch (\Exception $e) {
            $flashBag->add('error', $e->getMessage());
            $output = [];
        }
        
        return [
            'filename' => $filename,
            'pattern' => $pattern,
            'lines' => $lines, 
            'output' => $output,
            'caseSensitive' => $caseSensitive,
        ];
    }
}