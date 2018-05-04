<?php

/**
 *
 * @package phpBB Extension - Move Topics When Locked
 * @copyright (c) 2016 kasimi - https://kasimi.net
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace kasimi\movetopicswhenlocked\event;

use kasimi\movetopicswhenlocked\core\topic_mover;
use phpbb\auth\auth;
use phpbb\event\data;
use phpbb\request\request_interface;
use phpbb\user;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	/** @var topic_mover */
	protected $topic_mover;

	/** @var user */
	protected $user;

	/** @var auth */
	protected $auth;

	/** @var request_interface */
	protected $request;

	/** @var string */
	protected $root_path;

	/** @var string */
	protected $php_ext;

	/**
	 * @param topic_mover		$topic_mover
	 * @param user				$user
	 * @param auth				$auth
	 * @param request_interface	$request
	 * @param string			$root_path
	 * @param string			$php_ext
	 */
	public function __construct(
		topic_mover	$topic_mover,
		user $user,
		auth $auth,
		request_interface $request,
		$root_path,
		$php_ext
	)
	{
		$this->topic_mover	= $topic_mover;
		$this->user 		= $user;
		$this->auth			= $auth;
		$this->request		= $request;
		$this->root_path	= $root_path;
		$this->php_ext 		= $php_ext;
	}

	/**
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return [
			'core.posting_modify_submit_post_after'				=> 'posting_modify_submit_post_after',
			'tierra.topicsolved.mark_solved_after'				=> 'topic_solved_after',
			'alfredoramos.autolocktopics.topics_locked_after'	=> 'topics_locked_after',
		];
	}

	/**
	 * @param data $event
	 */
	public function posting_modify_submit_post_after($event)
	{
		$post_data = $event['post_data'];

		if ($post_data['topic_status'] == ITEM_UNLOCKED && $this->request->is_set_post('lock_topic'))
		{
			if ($this->auth->acl_get('m_lock', $event['forum_id']) || ($this->auth->acl_get('f_user_lock', $event['forum_id']) && $this->user->data['is_registered'] && !empty($post_data['topic_poster']) && $this->user->data['user_id'] == $post_data['topic_poster'] && $post_data['topic_status'] == ITEM_UNLOCKED))
			{
				$topic_data = [$event['post_data']['topic_id'] => $event['post_data']];
				$this->topic_mover->move_topics($topic_data, 'move_topics_when_locked');
			}
		}
	}

	/**
	 * @param data $event
	 */
	public function topic_solved_after($event)
	{
		if ($event['column_data']['topic_status'] == ITEM_LOCKED)
		{
			$topic_id = $event['topic_data']['topic_id'];
			$topic_data = $this->get_topic_data([$topic_id]);
			$this->topic_mover->move_topics($topic_data, 'move_topics_when_locked_solved');
		}
	}

	/**
	 * @param data $event
	 */
	public function topics_locked_after($event)
	{
		$topic_data = $this->get_topic_data($event['topic_ids']);
		$this->topic_mover->move_topics($topic_data, 'move_topics_when_locked_auto');
	}

	/**
	 * @param array $topic_ids
	 * @return array
	 */
	protected function get_topic_data(array $topic_ids)
	{
		if (!function_exists('phpbb_get_topic_data'))
		{
			include($this->root_path . 'includes/functions_mcp.' . $this->php_ext);
		}

		return phpbb_get_topic_data($topic_ids);
	}
}
