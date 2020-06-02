<?php

declare(strict_types=1);

/**
 * Flextype (http://flextype.org)
 * Founded by Sergey Romanenko and maintained by Flextype Community.
 */

namespace Flextype;

use Flextype\Component\Filesystem\Filesystem;
use Flextype\Component\Arr\Arr;
use Intervention\Image\ImageManagerStatic as Image;
use Slim\Http\Environment;
use Slim\Http\Uri;

class Media
{
    /**
     * Flextype Dependency Container
     *
     * @access private
     */
    private $flextype;

    /**
     * Constructor
     *
     * @access public
     */
    public function __construct($flextype)
    {
        $this->flextype = $flextype;
    }

    /**
     * Create a media file
     *
     * @param array  $file   Raw file data (multipart/form-data).
     * @param string $folder The folder you're targetting.
     *
     * @access public
     */
    public function createFile(array $file, string $folder)
    {
        $upload_folder = PATH['project'] . '/uploads/' . $folder . '/';
        $upload_metadata_folder = PATH['project'] . '/uploads/.meta/' . $folder . '/';

        if (! Filesystem::has($upload_folder)) {
            Filesystem::createDir($upload_folder);
        }

        if (! Filesystem::has($upload_metadata_folder)) {
            Filesystem::createDir($upload_metadata_folder);
        }

        $accept_file_types = $this->flextype['registry']->get('flextype.settings.media.accept_file_types');
        $max_file_size = $this->flextype['registry']->get('flextype.settings.media.max_file_size');
        $safe_names = $this->flextype['registry']->get('flextype.settings.media.safe_names');
        $max_image_width = $this->flextype['registry']->get('flextype.settings.media.max_image_width');
        $max_image_height = $this->flextype['registry']->get('flextype.settings.media.max_image_height');

        $exact = false;
        $chmod = 0644;
        $filename = null;
        $exif_data = [];

        //
        // Tests if a successful upload has been made.
        //
        if (isset($file['error'])
            and isset($file['tmp_name'])
            and $file['error'] === UPLOAD_ERR_OK
            and is_uploaded_file($file['tmp_name'])) {
            //
            // Tests if upload data is valid, even if no file was uploaded.
            //
            if (isset($file['error'])
                    and isset($file['name'])
                    and isset($file['type'])
                    and isset($file['tmp_name'])
                    and isset($file['size'])) {
                //
                // Test if an uploaded file is an allowed file type, by extension.
                //
                if (strpos($accept_file_types, strtolower(pathinfo($file['name'], PATHINFO_EXTENSION))) !== false) {
                    //
                    // Validation rule to test if an uploaded file is allowed by file size.
                    //
                    if (($file['error'] != UPLOAD_ERR_INI_SIZE)
                                  and ($file['error'] == UPLOAD_ERR_OK)
                                  and ($file['size'] <= $max_size)) {
                        //
                        // Validation rule to test if an upload is an image and, optionally, is the correct size.
                        //
                        if (in_array(mime_content_type($file['tmp_name']), ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
                            function validateImage($file, $max_image_width, $max_image_height, $exact)
                            {
                                try {
                                    // Get the width and height from the uploaded image
                                    list($width, $height) = getimagesize($file['tmp_name']);
                                } catch (ErrorException $e) {
                                    // Ignore read errors
                                }
                                if (empty($width) or empty($height)) {
                                    // Cannot get image size, cannot validate
                                    return false;
                                }
                                if (!$max_image_width) {
                                    // No limit, use the image width
                                    $max_image_width = $width;
                                }
                                if (!$max_image_height) {
                                    // No limit, use the image height
                                    $max_image_height = $height;
                                }
                                if ($exact) {
                                    // Check if dimensions match exactly
                                    return ($width === $max_image_width and $height === $max_image_height);
                                } else {
                                    // Check if size is within maximum dimensions
                                    return ($width <= $max_image_width and $height <= $max_image_height);
                                }
                                return false;
                            }
                            if (validateImage($file, $max_image_width, $max_image_height, $exact) === false) {
                                return false;
                            }
                        }
                        if (!isset($file['tmp_name']) or !is_uploaded_file($file['tmp_name'])) {
                            // Ignore corrupted uploads
                            return false;
                        }
                        if ($filename === null) {
                            // Use the default filename
                            $filename = $file['name'];
                        }
                        if ($safe_names === true) {
                            // Remove spaces from the filename
                            $filename = $this->flextype['slugify']->slugify(pathinfo($filename)['filename']) . '.' . pathinfo($filename)['extension'];
                        }
                        if (!is_dir($upload_folder) or !is_writable(realpath($upload_folder))) {
                            throw new \RuntimeException("Directory {$upload_folder} must be writable");
                        }
                        // Make the filename into a complete path
                        $filename = realpath($upload_folder) . DIRECTORY_SEPARATOR . $filename;
                        if (move_uploaded_file($file['tmp_name'], $filename)) {
                            // Set permissions on filename
                            chmod($filename, $chmod);

                            if (in_array(mime_content_type($filename), ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
                                // open an image file
                                $img = Image::make($filename);

                                // now you are able to resize the instance
                                if ($this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_width') > 0 && $this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_height') > 0) {
                                    $img->resize($this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_width'), $this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_height'), function($constraint) {
                                        $constraint->aspectRatio();
                                        $constraint->upsize();
                                    });
                                } elseif ($this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_width') > 0) {
                                    $img->resize($this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_width'), null, function($constraint) {
                                        $constraint->aspectRatio();
                                        $constraint->upsize();
                                    });
                                } elseif ($this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_height') > 0) {
                                    $img->resize(null, $this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_height'), function($constraint) {
                                        $constraint->aspectRatio();
                                        $constraint->upsize();
                                    });
                                }

                                // finally we save the image as a new file
                                $img->save($filename, $this->flextype['registry']->get('plugins.admin.settings.entries.media.upload_images_quality'));

                                // destroy
                                $img->destroy();


                                $headers = exif_read_data($filename);

                                $exif_data = [];

                                foreach ($headers['COMPUTED'] as $header => $value) {
                                    $exif_data[$header] = $value;
                                }
                            }

                            $metadata = ['title' => substr(basename($filename), 0, strrpos(basename($filename), '.')),
                                         'description' => '',
                                         'filename' => basename($filename),
                                         'type' => mime_content_type($filename),
                                         'filesize' => Filesystem::getSize($filename),
                                         'uploaded_on' => time(),
                                         'exif' => $exif_data];

                            Filesystem::write($upload_metadata_folder . basename($filename) . '.yaml',
                                              $this->flextype['serializer']->encode($metadata, 'yaml'));


                            // Return new file path
                            return $filename;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Fetch files collection
     *
     * @param string $folder The directory to list.
     *
     * @return array A list of file metadata.
     */
    public function fetchFilesCollection(string $folder) : array
    {
        $result = [];

        foreach (Filesystem::listContents($this->getDirMetaLocation($folder)) as $file) {
            $result[$file['basename']] = $this->flextype['serializer']->decode(Filesystem::read($file['path']), 'yaml');
            $result[$file['basename']]['url'] = 'project/uploads/' . $folder . '/' . $file['basename'];

            if ($this->flextype['registry']->has('flextype.settings.url') && $this->flextype['registry']->get('flextype.settings.url') != '') {
                $full_url = $this->flextype['registry']->get('flextype.settings.url');
            } else {
                $full_url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
            }

            $result[$file['basename']]['full_url'] = $full_url . '/project/uploads/' . $folder . '/' . $file['basename'];
        }

        return $result;
    }

    /**
     * Fetch single file
     *
     * @param string $directory The directory to list.
     *
     * @return array A list of file metadata.
     */
    public function fetchFilesSingle(string $id) : array
    {
        $result = [];

        if (Filesystem::has($this->getFileMetaLocation($id))) {
            $result = $this->flextype['serializer']->decode(Filesystem::read($this->getFileMetaLocation($id)), 'yaml');
            $result['url'] = 'project/uploads/' . $id;

            if ($this->flextype['registry']->has('flextype.settings.url') && $this->flextype['registry']->get('flextype.settings.url') != '') {
                $full_url = $this->flextype['registry']->get('flextype.settings.url');
            } else {
                $full_url = Uri::createFromEnvironment(new Environment($_SERVER))->getBaseUrl();
            }

            $result['full_url'] = $full_url . '/project/uploads/' . $id;
        }

        return $result;
    }

    /**
     * Create folder
     *
     * @param string $id Unique identifier of the folder.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function createFolder(string $id) : bool
    {
        if (!Filesystem::has($this->getDirLocation($id)) && !Filesystem::has($this->getDirMetaLocation($id))) {
            if (Filesystem::createDir($this->getDirLocation($id))) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Rename file
     *
     * @param string $id     Unique identifier of the file.
     * @param string $new_id New Unique identifier of the file.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function renameFile(string $id, string $new_id) : bool
    {
        if (!Filesystem::has($this->getFileLocation($new_id)) && !Filesystem::has($this->getFileMetaLocation($new_id))) {
            if (rename($this->getFileLocation($id), $this->getFileLocation($new_id)) && rename($this->getFileMetaLocation($id), $this->getFileMetaLocation($new_id))) {

                // Update meta file
                $file_data = $this->flextype['serializer']->decode(Filesystem::read($this->getFileMetaLocation($new_id)), 'yaml');
                $file_data['filename'] = basename($new_id);
                Filesystem::write($this->getFileMetaLocation($new_id), $this->flextype['serializer']->encode($file_data, 'yaml'));

                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Update file meta information
     *
     * @param string $id    Unique identifier of the file.
     * @param string $field Field name
     * @param string $value Field value
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function updateFileMeta(string $id, string $field, string $value) : bool
    {
        $file_data = $this->flextype['serializer']->decode(Filesystem::read($this->getFileMetaLocation($id)), 'yaml');

        if (Arr::keyExists($file_data, $field)) {
            Arr::set($file_data, $field, $value);
            return Filesystem::write($this->getFileMetaLocation($id), $this->flextype['serializer']->encode($file_data, 'yaml'));
        }

        return false;
    }

    /**
     * Add file meta information
     *
     * @param string $id    Unique identifier of the file.
     * @param string $field Field name
     * @param string $value Field value
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function addFileMeta(string $id, string $field, string $value) : bool
    {
        $file_data = $this->flextype['serializer']->decode(Filesystem::read($this->getFileMetaLocation($id)), 'yaml');

        if (!Arr::keyExists($file_data, $field)) {
            Arr::set($file_data, $field, $value);
            return Filesystem::write($this->getFileMetaLocation($id), $this->flextype['serializer']->encode($file_data, 'yaml'));
        }

        return false;
    }

    /**
     * Delete file meta information
     *
     * @param string $id    Unique identifier of the file.
     * @param string $field Field name
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function deleteFileMeta(string $id, string $field) : bool
    {
        $file_data = $this->flextype['serializer']->decode(Filesystem::read($this->getFileMetaLocation($id)), 'yaml');

        if (Arr::keyExists($file_data, $field)) {
            Arr::delete($file_data, $field);
            return Filesystem::write($this->getFileMetaLocation($id), $this->flextype['serializer']->encode($file_data, 'yaml'));
        }

        return false;
    }

    /**
     * Rename folder
     *
     * @param string $id     Unique identifier of the folder.
     * @param string $new_id New Unique identifier of the folder.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function renameFolder(string $id, string $new_id) : bool
    {
        if (!Filesystem::has($this->getDirLocation($new_id)) && !Filesystem::has($this->getDirMetaLocation($new_id))) {
            if (rename($this->getDirLocation($id), $this->getDirLocation($new_id)) && rename($this->getDirMetaLocation($id), $this->getDirMetaLocation($new_id))) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Delete file
     *
     * @param string $id Unique identifier of the file.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function deleteFile(string $id)
    {
        Filesystem::delete($this->getFileLocation($id));
        Filesystem::delete($this->getFileMetaLocation($id));
    }

    /**
     * Delete dir
     *
     * @param string $id Unique identifier of the file.
     *
     * @return bool True on success, false on failure.
     *
     * @access public
     */
    public function deleteFolder(string $id)
    {
        Filesystem::deleteDir($this->getDirLocation($id));
        Filesystem::deleteDir($this->getDirMetaLocation($id));
    }

    /**
     * Get file location
     *
     * @param string $id Unique identifier of the file.
     *
     * @return string entry file location
     *
     * @access public
     */
    public function getFileLocation(string $id) : string
    {
        return PATH['project'] . '/uploads/' . $id;
    }

    /**
     * Get file meta location
     *
     * @param string $id Unique identifier of the file.
     *
     * @return string entry file location
     *
     * @access public
     */
    public function getFileMetaLocation(string $id) : string
    {
        return PATH['project'] . '/uploads/.meta/' . $id . '.yaml';
    }

    /**
     * Get files directory location
     *
     * @param string $id Unique identifier of the folder.
     *
     * @return string entry directory location
     *
     * @access public
     */
    public function getDirLocation(string $id) : string
    {
        return PATH['project'] . '/uploads/' . $id;
    }

    /**
     * Get files directory meta location
     *
     * @param string $id Unique identifier of the folder.
     *
     * @return string entry directory location
     *
     * @access public
     */
    public function getDirMetaLocation(string $id) : string
    {
        return PATH['project'] . '/uploads/.meta/' . $id;
    }
}
