<?php


    namespace Framework\Component\Filesystem;

    use DirectoryIterator;
    use FilesystemIterator;
    use Illuminate\Filesystem\Filesystem as FilesystemBase;

    class Filesystem extends FilesystemBase
    {


        /**
         * Copy a directory from one location to another, except for a few files.
         *
         * @param  string $directory
         * @param  string $destination
         * @param  int    $options
         * @param array   $except
         * @return bool
         */
        public function copyDirectoryExcept($directory, $destination, $options = null, array $except =[])
        {
            if (! $this->isDirectory($directory)) {
                return false;
            }

            $options = $options ?: FilesystemIterator::SKIP_DOTS;

            // If the destination directory does not actually exist, we will go ahead and
            // create it recursively, which just gets the destination prepared to copy
            // the files over. Once we make the directory we'll proceed the copying.
            if (! $this->isDirectory($destination)) {
                $this->makeDirectory($destination, 0777, true);
            }

            $items = new FilesystemIterator($directory, $options);

            foreach ($items as $item) {

                if (in_array($item->getPathname(), $except)) {
                    continue;
                }

                // As we spin through items, we will check to see if the current file is actually
                // a directory or a file. When it is actually a directory we will need to call
                // back into this function recursively to keep copying these nested folders.
                $target = $destination.'/'.$item->getBasename();

                if ($item->isDir()) {

                    $path = $item->getPathname();

                    if (! $this->copyDirectoryExcept($path, $target, $options, $except)) {
                        return false;
                    }
                } else {

                    // If the current items is just a regular file, we will just copy this to the new
                    // location and keep looping. If for some reason the copy fails we'll bail out
                    // and return false, so the developer is aware that the copy process failed.

                    if (! $this->copy($item->getPathname(), $target)) {
                        return false;
                    }
                }
            }

            return true;
        }


        /**
         * Copy a directory from one location to another.
         *
         * @param array $files
         * @param       $origin
         * @param       $destination
         * @param  int  $options
         * @return bool
         * @internal param array $except
         * @internal param string $directory
         * @internal param string $destination
         */
        public function copyTheseFiles(array $files,$origin,$destination, $options = null)
        {

            foreach ($files as $file)
            {
                $file_origin        = $origin.'/'.$file;
                $file_destination   = $destination.'/'.$file;

                if(is_dir($file_origin))
                {
                    $this->copyDirectory($file_origin,$file_destination, $options);
                }else
                    $this->copy($file_origin, $file_destination);
            }

            return true;
        }


        /**
         * Cambios masivos hechos a un grupo de archivos.
         *
         * @param array $changes
         * @param       $origin
         * @param       $destination
         * @param  int  $options
         * @return bool
         */
        public function modifications(array $changes, $origin, $destination = null, $options = null)
        {
            foreach ($changes as $file => $change) {

                $origin_file = $origin.'/'.$file;

                if (isset($change['rename'])) {

                    $this->move($origin_file, $destination.'/'.$change['rename']);

                } elseif (isset($change['delete']) && $change['delete'] == true) {

                    if ($this->isFile($origin_file)) {

                        $this->delete($origin_file);

                    } else {
                        $this->deleteDirectory($origin_file);
                    }

                }
            }

            return true;
        }


        /**
         * Copy a directory from one location to another.
         *
         * @param array $files
         * @param       $directory
         * @return bool
         */
        public function deleteFiles(array $files, $directory)
        {
            foreach ($files as $file)
            {
                $file  = $directory.'/'.$file;

                if(is_dir($file))
                {
                    $this->deleteDirectory($file);
                }else
                    $this->delete($file);
            }

            return true;
        }

        /**
         * Assign permissions to a directory recursively.
         *
         * @param $path
         * @param $mode
         */
        function chmod_r($path, $mode) {

            $dir = new DirectoryIterator($path);
            foreach ($dir as $item) {
                chmod($item->getPathname(), $mode);
                if ($item->isDir() && !$item->isDot()) {
                    $this->chmod_r($item->getPathname(),$mode);
                }
            }
        }


    }