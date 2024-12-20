<?php

namespace JackBerck\Ambatuflexing\Controller;

use JackBerck\Ambatuflexing\App\Flasher;
use JackBerck\Ambatuflexing\App\View;
use JackBerck\Ambatuflexing\Config\Database;
use JackBerck\Ambatuflexing\Exception\ValidationException;
use JackBerck\Ambatuflexing\Model\AdminManageUsersRequest;
use JackBerck\Ambatuflexing\Model\AdminUpdateEmailUserRequest;
use JackBerck\Ambatuflexing\Model\AdminUpdatePasswordRequest;
use JackBerck\Ambatuflexing\Model\FindPostRequest;
use JackBerck\Ambatuflexing\Model\UserGetLikedPostRequest;
use JackBerck\Ambatuflexing\Repository\CommentRepository;
use JackBerck\Ambatuflexing\Repository\LikeRepository;
use JackBerck\Ambatuflexing\Repository\PostImageRepository;
use JackBerck\Ambatuflexing\Repository\PostRepository;
use JackBerck\Ambatuflexing\Repository\SessionRepository;
use JackBerck\Ambatuflexing\Repository\UserRepository;
use JackBerck\Ambatuflexing\Service\PostService;
use JackBerck\Ambatuflexing\Service\SessionService;
use JackBerck\Ambatuflexing\Service\UserService;

class AdminController
{
    private UserService $userService;
    private SessionService $sessionService;
    private PostService $postService;

    public function __construct()
    {
        $connection = Database::getConnection();
        $userRepository = new UserRepository($connection);
        $this->userService = new UserService($userRepository);

        $sessionRepository = new SessionRepository($connection);
        $this->sessionService = new SessionService($sessionRepository, $userRepository);

        $postRepository = new PostRepository($connection);
        $postImageRepository = new PostImageRepository($connection);
        $likeRepository = new LikeRepository($connection);
        $commentRepository = new CommentRepository($connection);

        $this->postService = new PostService($postRepository, $postImageRepository, $userRepository, $likeRepository, $commentRepository);
    }

    public function dashboard(): void
    {
        $user = $this->sessionService->current();

        View::render('Admin/dashboard', [
            "title" => "Dashboard Admin",
            "user" => (array)$user
        ]);
    }

    function managePosts(): void
    {
        $user = $this->sessionService->current();
        $model = [
            'title' => 'Manage Post'
        ];

        if ($user != null) {
            $model['user'] = (array)$user;
        }

        $req = new FindPostRequest();
        $req->title = $_GET['title'] ?? null;
        $req->category = $_GET['category'] ?? null;
        $req->userId = isset($_GET['userId']) && (int)$_GET['userId'] ? (int)$_GET['userId'] : null;
        $req->limit = 36;
        $req->page = isset($_GET['page']) && ((int)$_GET['page'] >= 0) ? (int)$_GET['page'] : 1;

        $res = $this->postService->search($req);
        $model["posts"] = $res->posts;
        $model["total"] = $res->totalPost;
        $model["limit"] = $req->limit;

        View::render('Admin/managePosts', $model);
    }

    function updatePost($postId): void
    {
        $user = $this->sessionService->current();

        $model = [
            'user' => (array)$user,
        ];

        try {
            $details = $this->postService->details($postId);

            $model['post'] = (array)$details->post;
            $model['author'] = $details->author;
            $model['authorPhoto'] = $details->authorPhoto;
            $model['authorPosition'] = $details->authorPosition;
            $model['images'] = $details->images;
            $model['title'] = $details->post->title;

            View::render('Admin/updatePost', $model);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/admin/manage-posts');
        }
    }

    public function likedPosts(): void
    {
        $user = $this->sessionService->current();

        $request = new UserGetLikedPostRequest();
        $request->userId = $user->id;
        $request->page = isset($_GET['page']) && ((int)$_GET['page'] >= 0) ? (int)$_GET['page'] : 1;
        $request->limit = 36;

        $response = $this->postService->likedPost($request);

        $model = [
            'user' => (array)$user,
            'title' => 'Liked Posts',
            'posts' => $response->likedPost,
            'total' => $response->totalPost,
            "limit" => $request->limit,
        ];

        View::render('Admin/likedPosts', $model);
    }

    public function manageUsers(): void
    {
        $user = $this->sessionService->current();
        $model = ['user' => (array)$user, 'title' => "Manage Users"];

        $req = new AdminManageUsersRequest();
        $req->limit = 50;
        $req->page = isset($_GET['page']) && (int)$_GET['page'] > 0 ? (int)$_GET['page'] : 1;
        $req->email = $_POST['email'] ?? null;
        $req->username = $_POST['username'] ?? null;
        $req->position = $_POST['position'] ?? null;

        $res = $this->userService->manage($req);

        $model['manageUsers'] = $res->users;
        $model["totalUsers"] = $res->totalUsers;
        $model["limit"] = $req->limit;
        View::render('Admin/manageUsers', $model);
    }

    public function updateUser($userId): void
    {
        $user = $this->sessionService->current();
        $model = ["user" => (array)$user];

        try {
            $model['updateUser'] = (array)$this->userService->findById($userId);
            View::render('Admin/updateUser', $model);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/admin/manage-users');
        }
    }

    public function putUpdateEmailUser($userId): void
    {
//        parse_str(file_get_contents("php://input"), $_PUT);

        $update = new AdminUpdateEmailUserRequest();
        $update->userId = $userId;
        $update->email = $_POST['email'] ?? null;

        try {
            $this->userService->updateEmail($update);
            Flasher::set("Success", 'Update Email user successfully');
            View::redirect('/admin/manage-users/' . $userId);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/admin/manage-users/' . $userId);
        }

    }

    public function patchUpdatePassword($userId): void
    {
//        parse_str(file_get_contents("php://input"), $_PATCH);

        $request = new AdminUpdatePasswordRequest();
        $request->userId = $userId;
        $request->newPassword = $_POST['newPassword'] ?? null;

        try {
            $this->userService->updatePasswordUser($request);
            Flasher::set("Success", 'Update Password user successfully');
            View::redirect("/admin/manage-users/" . $userId);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect("/admin/manage-users/" . $userId);
        }
    }

}