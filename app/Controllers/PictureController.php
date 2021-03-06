<?php

namespace App\Controllers;

use App\Models\Hashtag;
use App\Models\PictureRating;
use Intervention\Image\Image;
use Respect\Validation\Validator as v;
use Intervention\Image\ImageManager;
use App\Models\Picture;
use App\Upload\FileUploader;
use App\Upload\UploadedFile;

class PictureController extends Controller
{

    public function getAdd($request, $response)
    {
        return $this->view->render($response, 'picture/new.twig');
    }


    public function changeProfilePicture($request, $response)
    {
        $user = $this->auth->user();
        $redirectUrl = $this->router->pathFor('user.profile', ['slug' => $user->user_slug]);
        $file = new UploadedFile('picture-file', true);
        $path = 'uploads/images/users/';

        if (!$file->isValid()) {
            $this->flash->addMessage('error', 'An error occured while attempting to upload the image.');
            return $this->redirectTo($response, $redirectUrl);
        }

        if ($file->isUploaded()) {
            $fileUploader = new FileUploader($file, 2000000, $path, FileUploader::TYPE_IMG);

            if (!$fileUploader->checkExtension()) {
                $this->flash->addMessage('error', 'Unknown file extension.');
                return $this->redirectTo($response, $redirectUrl);
            }

            if (!$fileUploader->checkFileSize()) {
                $this->flash->addMessage('error', 'The file is too large. Max file size: 2MB.');
                return $this->redirectTo($response, $redirectUrl);
            }

            $image = $this->makeImage($file->getTempName());
            $image->save($path . $user->user_id . '.jpg');

            $this->flash->addMessage('success', 'Picture edited successfully!');
            return $this->redirectTo($response, $redirectUrl);
        }

        $this->flash->addMessage('error', 'An image file is required.');
        return $this->redirectTo($response, $redirectUrl);
    }

    public function postAdd($request, $response)
    {
        $redirectUrl = $this->router->pathFor('picture.add');
        $caption = $request->getParam('caption');
        $location = $request->getParam('location');

        if (!v::notBlank()->validate($caption)) {
            $this->flash->addMessage('error', 'The caption cannot be empty.');
            return $this->redirectTo($response, $redirectUrl);
        }

        if (!v::notBlank()->validate($location)) {
            $this->flash->addMessage('error', 'Please tell us where you bought your kebab.');
            return $this->redirectTo($response, $redirectUrl);
        }

        $file = new UploadedFile('picture-file', true);
         if (!$file->isValid()) {
             $this->flash->addMessage('error', 'An error occured while attempting to upload the image.');
             return $this->redirectTo($response, $redirectUrl);
         }

        if ($file->isUploaded()) {
            $fileUploader = new FileUploader($file, 2000000, 'uploads/images/kebabs', FileUploader::TYPE_IMG);

            if (!$fileUploader->checkExtension()) {
                $this->flash->addMessage('error', 'Unknown file extension.');
                return $this->redirectTo($response, $redirectUrl);
            }

            if (!$fileUploader->checkFileSize()) {
                $this->flash->addMessage('error', 'The file is too large. Max file size: 2MB.');
                return $this->redirectTo($response, $redirectUrl);
            }

            $picture = new Picture();
            $picture->description = $caption;
            $picture->location = $location;
            $picture->user()->associate($this->auth->user());
            $picture->save();

            $tags = array();
            preg_match_all('/#(\w+)/', $caption, $tags);

            foreach ($tags[1] as $tag) {
                $hashtag = Hashtag::where('name', $tag)->first();
                if (!$hashtag) {
                    $hashtag = new Hashtag();
                    $hashtag->name = $tag;
                    $hashtag->save();
                }

                $picture->hashtags()->attach($hashtag->id);
            }

            $image = $this->makeImage($file->getTempName());

            if (
                isset($_POST['crop-pic']) &&
                isset($_POST['x']) &&
                isset($_POST['y']) &&
                isset($_POST['x2']) &&
                isset($_POST['y2']) &&
                isset($_POST['width']) &&
                isset($_POST['height']) &&
                isset($_POST['original-width']) &&
                isset($_POST['original-height'])
            ) {
                $image = $this->crop(
                    $image,
                    (int) $_POST['x'],
                    (int) $_POST['y'],
                    (int) $_POST['x2'],
                    (int) $_POST['y2'],
                    (int) $_POST['width'],
                    (int) $_POST['height'],
                    (int) $_POST['original-width'],
                    (int) $_POST['original-height']
                );

                if ($_POST['width'] != $_POST['height']) {
                    $image = $this->resize($image);
                }
            } else {
                $image = $this->resize($image);
            }

            $image->save('uploads/images/kebabs/' . $picture->id . '.jpg');

            $this->flash->addMessage('success', 'Picture added successfully!');
            return $this->redirect($response, 'user.profile', [
                'slug' => $this->auth->user()->user_slug
            ]);
        }

        $this->flash->addMessage('error', 'An image file is required.');
        return $this->redirectTo($response, $redirectUrl);
    }

    public function getEdit($request, $response, $args)
    {
        $picture = Picture::find($args['id']);

        if (!$picture) {
            throw $this->notFoundException($request, $response);
        }

        if ($picture->user_id !== $this->auth->user()->user_id) {
            throw new \Exception('This picture does not belong to you!');
        }

        return $this->view->render($response, 'picture/edit.twig', [
            'picture' => $picture
        ]);
    }

    public function postEdit($request, $response, $args)
    {
        $picture = Picture::find($args['id']);

        if (!$picture) {
            throw $this->notFoundException($request, $response);
        }

        $user = $this->auth->user();

        if ($picture->user_id !== $user->user_id) {
            throw new \Exception('This picture does not belong to you!');
        }

        $caption = $request->getParam('caption');

        if (!v::notBlank()->validate($caption)) {
            $this->flash->addMessage('error', 'The caption cannot be empty.');
            return $this->redirect($response, 'picture.edit', [
                'id' => $picture->id
            ]);
        }

        // Parse tags in caption saved in database
        $oldTags = array();
        preg_match_all('/#(\w+)/', $picture->description, $oldTags);

        // Parse tags in caption submitted in the form
        $newTags = array();
        preg_match_all('/#(\w+)/', $caption, $newTags);

        // Difference between old and new caption
        $removedTags = array_diff($oldTags[1], $newTags[1]);
        $addedTags = array_diff($newTags[1], $oldTags[1]);

        Hashtag::saveHashtags($picture, $addedTags, $removedTags);

        $picture->description = $caption;
        $picture->save();

        $this->flash->addMessage('success', 'Picture edited successfully!');
        return $this->redirect($response, 'user.profile', [
            'slug' => $user->user_slug
        ]);
    }

    public function delete($request, $response, $args)
    {
        $picture = Picture::find($args['id']);

        if (!$picture) {
            throw $this->notFoundException($request, $response);
        }

        $user = $this->auth->user();

        if ($picture->user_id !== $user->user_id) {
            throw new \Exception('This picture does not belong to you!');
        }

        $picture->hashtags()->detach();
        $picture->comments()->delete();
        $picture->pictureRating()->delete();

        unlink(__DIR__ . '/../../public/' . $picture->getWebPath());

        $picture->delete();

        $this->flash->addMessage('success', 'Picture deleted successfully!');
        return $this->redirect($response, 'user.profile', ['slug' => $user->user_slug]);
    }

    private function makeImage($src)
    {
        $manager = new ImageManager(array('driver' => 'gd'));

        return $manager->make($src);
    }

    private function crop(Image $image, $x1, $y1, $x2, $y2, $w, $h, $ow, $oh)
    {
        $x = $x1 < $x2 ? $x1 : $x2;
        $y = $y1 < $y2 ? $y1 : $y2;

        $square = $w == $h;

        $x = (int) ceil(($x * $image->getWidth()) / $ow);
        $y = (int) ceil(($y * $image->getHeight()) / $oh);

        $w = (int) ceil(($w * $image->getWidth()) / $ow);
        $h = (int) ceil(($h * $image->getHeight()) / $oh);

        if ($square && $w != $h) {
            if ($w > $h) {
                $h = $w;
            } else {
                $w = $h;
            }
        }

        return $image->crop($w, $h, $x, $y);
    }

    private function resize(Image $image)
    {
        $size = $image->height() > $image->width() ? $image->height() : $image->width();

        return $image->resizeCanvas($size, $size, 'center', false, '#000000');
    }


    public function likeDispatcher($request, $response)
    {
        $action = $request->getParam('what');
        $idPhoto = $request->getParam('idPhoto');
        $userTarget = $request->getParam('userTarget');
        $idUser = $this->auth->user()->user_id;

        if ($action && $idPhoto && $idUser && $userTarget) {
            switch ($action) {
                case 'like' :
                    $this->likePhoto($idUser, $idPhoto);
                    $this->flash->addMessage('success', '<i class="material-icons">thumb_up</i> Photo liked');
                    return $this->redirectTo($response, 'user/'.$userTarget);
                case 'dislike' :
                    $this->dislikePhoto($idUser,$idPhoto);
                    $this->flash->addMessage('error', '<i class="material-icons">thumb_down</i> Photo disliked');
                    return $this->redirectTo($response, 'user/'.$userTarget);
            }
        }

        $this->flash->addMessage('error', 'You just tried a weird thing.');
        return $this->redirectTo($response, '.');
    }

    private function isLiked($idUser, $idPhoto)
    {
        $query = PictureRating::where('user_id', '=', $idUser)
                    ->where('picture_id', '=', $idPhoto)
                    ->first();

        if ($query == NULL) {
            return false;
        }

        return true;
    }

    private function dislikePhoto($idUser, $idPhoto)
    {
        if ($this->isLiked($idUser,$idPhoto)) {

            $like = PictureRating::where('user_id', '=', $idUser)
                ->where('picture_id', '=', $idPhoto)
                ->first();

            $like->delete();
        }
    }

    private function likePhoto($idUser, $idPhoto)
    {
        if ( !($this->isLiked($idUser, $idPhoto)) ) {
            $pictureRate = new PictureRating();
            $pictureRate->liker($idUser, $idPhoto);
            $pictureRate->save();
        }
    }
}
