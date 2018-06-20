<?php
namespace bateszi\XenForo2API\Pub\Controller;

use XF\Entity\Forum;
use XF\Entity\Node;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Html\Renderer\BbCode;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class API extends AbstractController {

	public function checkCsrfIfNeeded($action, ParameterBag $params) {
		return;
	}

	public function actionForums() {
		$forums = [];
		$finder = $this->finder('XF:Forum');
		$forumsModels = $finder->fetch();

		foreach ( $forumsModels as $forumModel ) {
			/**
			 * @var $forumModel Forum
			 * @var $nodeModel Node
			 */
			$nodeModel = $forumModel->getRelation('Node');
			$forums[$forumModel->node_id] = $nodeModel->title;
		}

		$this->setResponseType('json');

		return $this->view('bateszi\XenForo2API:Reply', '', [
			'forums' => $forums,
		]);
	}

	public function actionThreads(ParameterBag $params) {
		$threads = [];
		$threadIds = explode(',', $params['thread_ids']);

		foreach ( $threadIds as $threadId ) {
			$finder = $this->finder('XF:Thread');

			/**
			 * @var $thread Thread
			 */
			$thread = $finder->where('thread_id', (int)$threadId)->fetchOne();

			if ($thread) {
				$relativeUrl = $this->app()->router('public')->buildLink('threads', $thread);
				$absoluteUrl = $this->app()->request()->convertToAbsoluteUri($relativeUrl);

				$threads[$threadId] = [
					'replyCount' => $thread->reply_count,
					'url' => $absoluteUrl,
				];
			}
		}

		$this->setResponseType('json');

		return $this->view('bateszi\XenForo2API:Reply', '', [
			'threads' => $threads,
		]);
	}

	public function actionThread() {

		$this->authenticate();

		$data = json_decode( $this->app()->request()->getInputRaw(), true );
		$this->setResponseType( 'json' );

		switch (strtolower($_SERVER['REQUEST_METHOD'])) {
			case 'post':
				return $this->createThread( $data );

			case 'put':
				return $this->updateThread( $data );

			case 'delete':
				return $this->deleteThread( $data );

			default:
				return $this->error('Invalid request. Do not repeat', 400);
		}

	}

	private function authenticate() {
		$config = require __DIR__ . '/../../config.php';

		if (isset($_SERVER['PHP_AUTH_USER'])
		    && isset($_SERVER['PHP_AUTH_PW'])
		    && $_SERVER['PHP_AUTH_USER'] === $config['user']
		    && $_SERVER['PHP_AUTH_PW'] === $config['pass']
		) {
			return;
		}

		header('WWW-Authenticate: Basic realm="My Realm"');
		header('HTTP/1.0 401 Unauthorized');
		echo 'Invalid credentials supplied';
		exit;
	}

	private function deleteThread( array $data ) {
		try {
			$finder = $this->finder( 'XF:Thread' );
			$thread = $finder->where( 'thread_id',  $data['threadId'] )->fetchOne();

			/**
			 * @var Thread $thread
			 */
			if ($thread) {
				$deleted = $thread->delete();
			} else {
				$deleted = 0;
			}

			return $this->view( 'bateszi\XenForo2API:Reply', '', [
				'deleted' => ( $deleted > 0 ),
			] );
		} catch (\Exception $e) {
		}
	}

	private function createThread( array $data ) {
		$threadBodyBBCode = BbCode::renderFromHtml( nl2br( $data['threadBodyHtml'] ) );

		/**
		 * @var $forum Forum
		 */
		$forumFinder = $this->finder( 'XF:Forum' );
		$forum       = $forumFinder->where( 'node_id', $data['forumId'] )->fetchOne();

		/**
		 * @var $user User
		 */
		$userFinder = $this->finder( 'XF:User' );
		$user       = $userFinder->where( 'user_id',  $data['userId'] )->fetchOne();

		/**
		 * @var $newThread Thread
		 */
		$newThread = $forum->getNewThread();
		$newThread->set( 'title', html_entity_decode( $data['threadTitle'] ) );
		$newThread->set( 'user_id', $user->user_id );
		$newThread->set( 'username', $user->username );
		$newThread->set( 'post_date', \XF::$time );

		try {

			$newThread->save();

			/**
			 * @var $newPost Post
			 */
			$newPost = $newThread->getNewPost();
			$newPost->set( 'user_id', $user->user_id );
			$newPost->set( 'username', $user->username );
			$newPost->set( 'post_date', \XF::$time );
			$newPost->set( 'message', $threadBodyBBCode );
			$newPost->set( 'position', 0 );
			$newPost->save();

			return $this->view( 'bateszi\XenForo2API:Reply', '', [
				'thread' => (int)$newThread->thread_id,
			] );

		} catch ( \Exception $e ) {
		}
	}

	private function updateThread( array $data ) {
		$threadFinder = $this->finder( 'XF:Thread' );
		$thread = $threadFinder->with('FirstPost')->where( 'thread_id',  $data['threadId'] )->fetchOne();

		if ($thread) {
			$post = $thread->getRelation('FirstPost');

			/**
			 * @var $thread Thread
			 * @var $post Post
			 */
			$post->set( 'message', BbCode::renderFromHtml( nl2br( $data['threadBodyHtml'] ) ));
			$post->set( 'last_edit_date', \XF::$time );
			$post->set( 'last_edit_user_id', $data['userId'] );
			$post->set( 'edit_count', ( $post->edit_count + 1 ) );

			try {

				$post->save();
				$thread->set( 'title', html_entity_decode( $data['threadTitle'] ) );
				$thread->save();

				return $this->view( 'bateszi\XenForo2API:Reply', '', [
					'updated' => $post->last_edit_date,
				] );

			} catch (\Exception $e) {
			}
		} else {
			$this->error('Thread does not exist. Do not repeat this request', 400);
		}
	}

}
