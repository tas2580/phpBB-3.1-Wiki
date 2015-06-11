<?php
/**
*
* @package phpBB Extension - Wiki
 * @copyright (c) 2015 tas2580 (https://tas2580.net)
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace tas2580\wiki\wiki;

class edit
{

	/* @var \phpbb\auth\auth */
	protected $auth;
	/* @var \phpbb\db\driver\driver */
	protected $db;
	/* @var \phpbb\controller\helper */
	protected $helper;
	/* @var \phpbb\template\template */
	protected $template;
	/* @var \phpbb\user */
	protected $user;
	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/**
	* Constructor
	*
	* @param \phpbb\auth\auth			$auth				Auth object
	* @param \phpbb\controller\helper	$helper				Controller helper object
	* @param \phpbb\template\template	$template			Template object
	* @param \phpbb\user				$user
	* @param string						$phpbb_root_path
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\db\driver\driver_interface $db, \phpbb\controller\helper $helper, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, $article_table, $phpbb_root_path, $php_ext)
	{
		$this->auth = $auth;
		$this->db = $db;
		$this->helper = $helper;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		// Extension tables
		$this->table_article = $article_table;
	}




	/**
	 * Edit an article
	 *
	 * @param	string	$article	URL of the article
	 * @return	object
	 */
	public function edit_article($article)
	{
		// If no auth to edit display error message
		if(!$this->auth->acl_get('u_wiki_edit'))
		{
			trigger_error('NO_ARTICLE');
		}
		$preview = $this->request->is_set_post('preview');
		$submit = $this->request->is_set_post('submit');

		$title = $this->request->variable('title', '', true);
		$message = $this->request->variable('message', '', true);
		$edit_reason = $this->request->variable('edit_reason', '', true);
		$topic_id = $this->request->variable('topic_id', '', true);
		// Display the preview
		if($preview)
		{
			$preview_text = $message;
			$uid = $bitfield = $options = '';
			$allowed_bbcode = $allowed_smilies = $allowed_urls = true;
			generate_text_for_storage($preview_text, $uid, $bitfield, $options, true, true, true);
			$preview_text = generate_text_for_display($preview_text, $uid, $bitfield, $options);
			$this->template->assign_vars(array(
				'S_PREVIEW'				=> true,
				'TITLE'					=> $title,
				'PREVIEW_MESSAGE'			=> $preview_text,
				'MESSAGE'				=> $message,
				'EDIT_REASON'				=> $edit_reason,
				'TOPIC_ID'					=> $topic_id,
			));
		}
		// Submit the article to database
		elseif($submit)
		{
			generate_text_for_storage($message, $uid, $bitfield, $options, true, true, true);
			$sql_data = array(
				'article_title'			=> $title,
				'article_url'				=> $article,
				'article_text'			=> $message,
				'bbcode_uid'			=> $uid,
				'bbcode_bitfield'		=> $bitfield,
				'article_approved'		=> 1,
				'article_user_id'			=> $this->user->data['user_id'],
				'article_last_edit'		=> time(),
				'article_edit_reason'		=> $edit_reason,
				'article_topic_id'			=> (int) $topic_id,
			);
			$sql = 'INSERT INTO ' . $this->table_article . '
				' . $this->db->sql_build_array('INSERT', $sql_data);
			$this->db->sql_query($sql);

			$back_url = empty($article) ? $this->helper->route('tas2580_wiki_index', array()) : $this->helper->route('tas2580_wiki_article', array('article'	=> $article));
			trigger_error($this->user->lang['EDIT_ARTICLE_SUCCESS'] . '<br /><br /><a href="' . $back_url . '">' . $this->user->lang['BACK_TO_ARTICLE'] . '</a>');
		}
		else
		{

			// Get the last version of the article to edit
			$this->user->add_lang('posting');
			$sql = 'SELECT *
				FROM ' . $this->table_article . '
				WHERE article_url = "' . $this->db->sql_escape($article) . '"
				ORDER BY article_last_edit DESC';
			$result = $this->db->sql_query_limit($sql, 1);
			$this->data = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);
			generate_smilies('inline', 0);
			display_custom_bbcodes();
			add_form_key('article');
			$message = generate_text_for_edit($this->data['article_text'], $this->data['bbcode_uid'], 3);
			$this->template->assign_vars(array(
				'TITLE'					=> $this->data['article_title'],
				'MESSAGE'				=> $message['text'],
				'S_BBCODE_ALLOWED'		=> 1,
				'TOPIC_ID'					=> $this->data['article_topic_id'],
			));

			if(!empty($article))
			{
				$this->template->assign_block_vars('navlinks', array(
					'FORUM_NAME'	=> $this->data['article_title'],
					'U_VIEW_FORUM'	=> $this->helper->route('tas2580_wiki_article', array('article'	=> $article)),
				));
			}
		}
		return $this->helper->render('article_edit.html', $this->user->lang['EDIT_WIKI']);
	}

}