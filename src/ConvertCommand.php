<?php

namespace DenisBeliaev\Webp;

use Exception;
use Symfony\Component\Console\{Command\Command,
    Input\InputArgument,
    Input\InputInterface,
    Input\InputOption,
    Output\OutputInterface,
    Question\ConfirmationQuestion};
use Symfony\Component\Finder\Finder;

class ConvertCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'convert';

    protected function configure()
    {
        $this
            ->addArgument('folder', InputArgument::REQUIRED, 'Folder for search images')
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                'The lower limit of file size to convert in KB',
                0
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $folder = realpath($input->getArgument('folder'));
        $limit = $input->getOption('limit');

        if (empty($folder)) {
            $output->writeln("Can't find path " . $input->getArgument('folder'));
            return Command::FAILURE;
        }

        $finder = new Finder();
        $finder
            ->files()
            ->name('/\.jpe?g$/')
            ->size(">= $limit K")
            ->in($folder);

        $message = 'Found: ' . $finder->count() . ' files';
        if (!empty($limit)) {
            $message .= " with size more than $limit KB";
        }
        $output->writeln($message);

        $bytes = 0;
        $converted = 0;
        if ($finder->count() > 0) {
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                'Convert this images? All converted images will be removed. [y/N] ',
                false
            );

            if ($helper->ask($input, $output, $question)) {
                foreach ($finder as $jpeg) {
                    $image = imagecreatefromjpeg($jpeg->getPathname());
                    if (!imagesetinterpolation($image, IMG_BICUBIC)) {
                        throw new Exception('Can not set interpolate');
                    }
                    $webp = preg_replace('/\.jpe?g$/i', '.webp', $jpeg->getPathname());
                    imagewebp($image, $webp, 88);
                    imagedestroy($image);

                    $size = filesize($webp);
                    if ($size > $jpeg->getSize()) {
                        unlink($webp);
                    } else {
                        $bytes += $jpeg->getSize() - $size;
                        unlink($jpeg->getPathname());
                        $converted++;
                    }
                }

                $output->writeln(
                    "Total converted images: $converted. Total reduced size: " . $this->humanFileSize($bytes)
                );
            }
        }
        return Command::SUCCESS;
    }

    private function humanFileSize($size, $unit = "")
    {
        if ((!$unit && $size >= 1 << 30) || $unit == "GB") {
            return number_format($size / (1 << 30), 2) . "GB";
        }
        if ((!$unit && $size >= 1 << 20) || $unit == "MB") {
            return number_format($size / (1 << 20), 2) . "MB";
        }
        if ((!$unit && $size >= 1 << 10) || $unit == "KB") {
            return number_format($size / (1 << 10), 2) . "KB";
        }
        return number_format($size) . " bytes";
    }
}
