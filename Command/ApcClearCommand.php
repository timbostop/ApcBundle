<?php

namespace Ornicar\ApcBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Loads initial data
 */
class ApcClearCommand extends ContainerAwareCommand
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array())
            ->addOption('opcode', null, InputOption::VALUE_NONE, 'Clear only opcode cache')
            ->addOption('user', null, InputOption::VALUE_NONE, 'Clear only user cache')
            ->setName('apc:clear')
        ;
    }

    /**
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $clearOpcode = $input->getOption('opcode') || !$input->getOption('user');
        $clearUser = $input->getOption('user') || !$input->getOption('opcode');

        $webDir = $this->getContainer()->getParameter('ornicar_apc.web_dir');
        if (!is_dir($webDir)) {
            throw new \InvalidArgumentException(sprintf('Web dir does not exist "%s"', $webDir));
        }
        if (!is_writable($webDir)) {
            throw new \InvalidArgumentException(sprintf('Web dir is not writable "%s"', $webDir));
        }
        $filename = 'apc22253278b41cfdbdd49c824c0f3e6d67.php';
        $file = $webDir.'/'.$filename;

        $templateFile = __DIR__.'/../Resources/template.tpl';
        $template = file_get_contents($templateFile);
        $code = strtr($template, array(
            '%user%' => var_export($clearUser, true),
            '%opcode%' => var_export($clearOpcode, true)
        ));

        //expects to find the file hardcoded at the location above
        //if (false === @file_put_contents($file, $code)) {
        //    throw new \RuntimeException(sprintf('Unable to write "%s"', $file));
        //}
        
        if (false === @file_get_contents($file)) {
            throw new \RuntimeException(sprintf('Unable to find "%s"', $file));
        }


        $url = $this->getContainer()->getParameter('ornicar_apc.host').'/'.$filename;

        if ($this->getContainer()->getParameter('ornicar_apc.mode') == 'fopen') {
            try {
                $result = file_get_contents($url);

                if (!$result) {
                    //unlink($file);
                    throw new \RuntimeException(sprintf('Unable to read "%s", does the host locally resolve?', $url));
                }
            } catch (\ErrorException $e) {
                //unlink($file);
                throw new \RuntimeException(sprintf('Unable to read "%s", does the host locally resolve?', $url));
            }
        }
        else {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_HEADER => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FAILONERROR => true
            ));

            $result = curl_exec($ch);

            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                //unlink($file);
                throw new \RuntimeException(sprintf('Curl error reading "%s": %s', $url, $error));
            }
            curl_close($ch);
        }

        $result = json_decode($result, true);
        //unlink($file);

        if($result['success']) {
            $output->writeLn($result['message']);
        } else {
            throw new \RuntimeException($result['message']);
        }
    }
}
