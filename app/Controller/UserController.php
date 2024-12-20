<?php

namespace JackBerck\Ambatuflexing\Controller;

use JackBerck\Ambatuflexing\App\Flasher;
use JackBerck\Ambatuflexing\App\Router;
use JackBerck\Ambatuflexing\App\View;
use JackBerck\Ambatuflexing\Config\Database;
use JackBerck\Ambatuflexing\Exception\ValidationException;
use JackBerck\Ambatuflexing\Model\FindPostRequest;
use JackBerck\Ambatuflexing\Model\UserCommentPostRequest;
use JackBerck\Ambatuflexing\Model\UserDeletePostRequest;
use JackBerck\Ambatuflexing\Model\UserDislikePostRequest;
use JackBerck\Ambatuflexing\Model\UserGetLikedPostRequest;
use JackBerck\Ambatuflexing\Model\UserLikePostRequest;
use JackBerck\Ambatuflexing\Model\UserLoginRequest;
use JackBerck\Ambatuflexing\Model\UserPasswordUpdateRequest;
use JackBerck\Ambatuflexing\Model\UserProfileUpdateRequest;
use JackBerck\Ambatuflexing\Model\UserRegisterRequest;
use JackBerck\Ambatuflexing\Model\UserRemoveCommentPostRequest;
use JackBerck\Ambatuflexing\Model\UserUpdatePostRequest;
use JackBerck\Ambatuflexing\Repository\CommentRepository;
use JackBerck\Ambatuflexing\Repository\LikeRepository;
use JackBerck\Ambatuflexing\Repository\PostImageRepository;
use JackBerck\Ambatuflexing\Repository\PostRepository;
use JackBerck\Ambatuflexing\Repository\SessionRepository;
use JackBerck\Ambatuflexing\Repository\UserRepository;
use JackBerck\Ambatuflexing\Service\PostService;
use JackBerck\Ambatuflexing\Service\SessionService;
use JackBerck\Ambatuflexing\Service\UserService;

class UserController
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

    public function register(): void
    {
        View::render('User/register', [
            'title' => 'Register',
        ]);
    }

    public function postRegister(): void
    {
        $request = new UserRegisterRequest();
        $request->email = $_POST['email'] ?? null;
        $request->username = $_POST['username'] ?? null;
        $request->password = $_POST['password'] ?? null;

        try {
            $this->userService->register($request);
            // Flasher::set("Success", "Register Success");
            View::redirect('/login');
        } catch (ValidationException $exception) {
            // Flasher::set("Error", $exception->getMessage(), "error");
            $_SESSION["message"] = [ "type" => "error", "text" => $exception->getMessage()];
            View::redirect('/register');
        }
    }

    public function login(): void
    {
        View::render('User/login', [
            "title" => "Login"
        ]);
    }

    public function postLogin(): void
    {
        $request = new UserLoginRequest();
        $request->email = $_POST['email'] ?? null;
        $request->password = $_POST['password'] ?? null;
        try {
            $response = $this->userService->login($request);
            $this->sessionService->create($response->user->id);
            View::redirect('/');
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            $_SESSION["message"] = [ "type" => "error", "text" => "Error, cannot register. Please try again!"];
            View::redirect('/login');
        }
    }

    public function logout(): void
    {
        $this->sessionService->destroy();
        View::redirect("/");
    }

    public function dashboard(): void
    {
        $user = $this->sessionService->current();

        View::render('User/dashboard', [
            "title" => "Dashboard",
            "user" => (array)$user
        ]);
    }

    public function postUpdateProfile(): void
    {
        $user = $this->sessionService->current();
        $redirect = $user->isAdmin == 'user' ? '/user/dashboard' : '/admin/dashboard';

        // Mengambil data dari PUT request
//        parse_str(file_get_contents("php://input"), $_PUT);

        $request = new UserProfileUpdateRequest();
        $request->id = $user->id;
        $request->username = $_POST['username'] ?? null;
        $request->position = $_POST['position'] ?? null;
        $request->bio = $_POST['bio'] ?? null;
        $request->photo = isset($_FILES['profile']) && $_FILES['profile']['tmp_name'] ? $_FILES['profile'] : null;
        try {
            $this->userService->updateProfile($request);
            Flasher::set("Success", 'Update successfully');
            View::redirect($redirect);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect($redirect);
        }
    }

    public function patchUpdatePassword(): void
    {
        $user = $this->sessionService->current();
        $redirect = $user->isAdmin == 'user' ? '/user/dashboard' : '/admin/dashboard';

//        parse_str(file_get_contents("php://input"), $_PATCH);

        $request = new UserPasswordUpdateRequest();
        $request->id = $user->id;
        $request->oldPassword = $_POST['oldPassword'];
        $request->newPassword = $_POST['newPassword'];

        try {
            $this->userService->updatePassword($request);
            Flasher::set("Success", 'Update Password successfully');
            View::redirect($redirect);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect($redirect);
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

        View::render('User/likedPosts', $model);
    }

    public function postLike($postId): void
    {
        $user = $this->sessionService->current();

        $request = new UserLikePostRequest();
        $request->postId = (int)$postId;
        $request->userId = $user->id;
        try {
            $this->postService->like($request);
            View::redirect('/post/' . $postId);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/post/' . $postId);
        }
    }

    public function dislike(): void
    {
        $user = $this->sessionService->current();
        $redirect = $user->isAdmin == 'user' ? '/user/liked-posts' : '/admin/liked-posts';

        $getRedirect = isset($_GET["redirect"]) && ($_GET["redirect"] !== "") ? $_GET["redirect"] : false;
        if ($getRedirect) $redirect = $getRedirect;

//        parse_str(file_get_contents("php://input"), $_DELETE);

        $request = new UserDislikePostRequest();
        $request->postId = (int)$_POST['postId'] ?? null;
        $request->userId = $user->id;

        try {
            $this->postService->dislike($request);
            View::redirect($redirect);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect($redirect);
        }
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
        $req->userId = $user->id;
        $req->limit = 36;
        $req->page = isset($_GET['page']) && ((int)$_GET['page'] >= 0) ? (int)$_GET['page'] : 1;

        $res = $this->postService->search($req);
        $model["posts"] = $res->posts;
        $model["total"] = $res->totalPost;
        $model["limit"] = $req->limit;

        View::render('User/managePosts', $model);
    }

    function deletePost(): void
    {
        $user = $this->sessionService->current();
        $redirect = $user->isAdmin == 'user' ? '/user/manage-posts' : '/admin/manage-posts';
//        parse_str(file_get_contents("php://input"), $_DELETE);

        $request = new UserDeletePostRequest();
        $request->postId = (int)$_POST['postId'] ?? null;
        $request->userId = $user->id;

        try {
            $this->postService->remove($request);
            Flasher::set("Success", 'Post deleted successfully');
            View::redirect($redirect);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect($redirect);
        }
    }

    function updatePost($postId): void
    {
        $user = $this->sessionService->current();

        $model = [
            'user' => (array)$user,
        ];

        try {
            $details = $this->postService->details($postId);

            if ($user->id != $details->post->authorId && $user->isAdmin != 'admin') throw new ValidationException("Error Cannot update this post");

            $model['post'] = (array)$details->post;
            $model['author'] = $details->author;
            $model['authorPhoto'] = $details->authorPhoto;
            $model['authorPosition'] = $details->authorPosition;
            $model['images'] = $details->images;
            $model['title'] = "Update " . $details->post->title;

            View::render('User/updatePost', $model);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/user/dashboard/manage-posts');
        }
    }

    function putUpdatePost($postId): void
    {
        $user = $this->sessionService->current();
        $redirect = $user->isAdmin == 'user' ? '/user/manage-posts' : '/admin/manage-posts';

//        parse_str(file_get_contents("php://input"), $_PUT);

        $req = new UserUpdatePostRequest();
        $req->userId = $user->id;
        $req->postId = (int)$postId;
        $req->title = $_POST['title'] ?? null;
        $req->content = $_POST['content'] ?? null;
        $req->category = $_POST['category'] ?? null;

        try {
            $this->postService->update($req);
            Flasher::set("Success", 'Update Post successfully');
            View::redirect($redirect);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect($redirect);
        }

    }

    function postComment($postId): void
    {
        $user = $this->sessionService->current();

        $req = new UserCommentPostRequest();
        $req->postId = (int)$postId;
        $req->userId = $user->id;
        $req->comment = $_POST['comment'] ?? null;

        try {
            $this->postService->comment($req);
            View::redirect('/post/' . $postId);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect('/post/' . $postId);
        }
    }

    public function deleteComment($postId): void
    {
        $user = $this->sessionService->current();

        $req = new UserRemoveCommentPostRequest();
        $req->userId = $user->id;
        $req->postId = $postId;
        $req->commentId = $_POST['commentId'] ?? null;
        try {
            $this->postService->removeComment($req);
            View::redirect('/post/' . $postId);
        } catch (ValidationException $exception) {
            Flasher::set("Error", $exception->getMessage(), "error");
            View::redirect("/post/" . $postId);
        }
    }

    public function detailUser($userId): void
    {
        $user = $this->sessionService->current();

        $model = [
            'user' => (array)$user
        ];

        try {
            $userSearch = $this->userService->findById($userId);

            $req = new FindPostRequest();
            $req->userId = $userId;
            $req->limit = 36;
            $req->page = isset($_GET['page']) && ((int)$_GET['page'] >= 0) ? (int)$_GET['page'] : 1;
            $res = $this->postService->search($req);

            $model["profile"] = (array)$userSearch;
            $model["posts"] = $res->posts;
            $model["total"] = $res->totalPost;
            $model["limit"] = $req->limit;

            View::render("User/profile", $model);
        } catch (ValidationException $exception) {
            Flasher::set("Not Found", $exception->getMessage(), "error");
            View::redirect("/");
        }
    }


}
