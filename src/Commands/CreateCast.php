<?php

declare(strict_types=1);

namespace Wmt\NovaFlexibleContent\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CreateCast extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flexible:cast {classname? : The cast\'s classname}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a new Flexible Content cast class';

    /**
     * The layout's classname
     *
     * @var string
     */
    protected $classname;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->classname = $this->getClassnameArgument();

        $path = $this->getPath();

        $this->files->put($path, $this->buildClass());

        $this->info('Created ' . $path);
    }

    /**
     * Get the classname
     *
     * @return string
     */
    public function getClassnameArgument()
    {
        if (!$this->argument('classname')) {
            return $this->ask('Please provide a class name for your layout');
        }

        return $this->argument('classname');
    }

    /**
     * Build the layout's file path
     *
     * @return string
     */
    protected function getPath()
    {
        return $this->makeDirectory(
            app_path('Casts/' . $this->classname . '.php')
        );
    }

    /**
     * Create the directories if they do not exist yet
     *
     * @param string $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        $directory = dirname($path);

        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true, true);
        }

        return $path;
    }

    /**
     * Generate the file's content
     *
     * @return string
     */
    protected function buildClass()
    {
        return str_replace(
            [
                ':classname',
            ],
            [
                $this->classname,
            ],
            $this->files->get(__DIR__ . '/../Stubs/Cast.stub')
        );
    }
}
