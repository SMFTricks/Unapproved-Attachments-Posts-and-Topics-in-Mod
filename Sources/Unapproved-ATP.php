<?php

/**
 * @package Unapproved Attachments, Posts and Topics in Mod
 * @version 1.0
 * @author Diego Andrés <diegoandres_cortes@outlook.com>
 * @copyright Copyright (c) 2021, Diego Andrés
 * @license https://www.mozilla.org/en-US/MPL/2.0/
 */

if (!defined('SMF'))
	die('No direct access...');

class UnapprovedATP
{
	/**
	 * @var array The list of boads user can approve
	 */
	private $_approve_boards;

	/**
	 * @var int The unnaproved posts count
	 */
	private $_posts;

	/**
	 * @var int The unnaproved topics count
	 */
	private $_topics;

	/**
	 * @var int The unnaproved attachments count
	 */
	private $_attachments_count;

	/**
	 * UnapprovedATP::__construct()
	 *
	 *  Initialize the notis to 0 and then start adding them
	 * 
	 * @return void
	 */
	public function __construct()
	{
		global $user_info;

		// Boards user can approve
		$this->_approve_boards = $user_info['mod_cache']['ap'];

		// Don't query for users that can't approve
		if (!empty($this->_approve_boards))
		{
			// Posts and topics
			$this->posts();

			// Attachments
			$this->attachments();
		}
	}

	/**
	 * UnapprovedATP::current_action()
	 *
	 *  Insert the notis in the title
	 * 
	 * @return void
	 */
	public function current_action()
	{
		global $context;

		// Mod center button?
		if (!empty(!empty($context['menu_buttons']['moderate'])))
		{
			// Posts
			if (!empty($this->_posts) || !empty($this->_topics))
			{
				// Mod Button
				if (!empty($context['menu_buttons']['moderate']['amt']))
					$context['menu_buttons']['moderate']['amt'] += $this->_posts + $this->_topics;
				else
					$context['menu_buttons']['moderate']['amt'] = $this->_posts + $this->_topics;

				// Area?
				$context['menu_buttons']['moderate']['sub_buttons']['poststopics']['amt'] = $this->_posts + $this->_topics;
			}

			// Attachments
			if (!empty($this->_attachments_count))
			{
				// Mod Button
				if (!empty($context['menu_buttons']['moderate']['amt']))
					$context['menu_buttons']['moderate']['amt'] += $this->_attachments_count;
				else
					$context['menu_buttons']['moderate']['amt'] = $this->_attachments_count;

				// Area?
				$context['menu_buttons']['moderate']['sub_buttons']['attachments']['amt'] = $this->_attachments_count;
			}
		}

		// Add if to the total
		$context['total_mod_reports'] += $this->_posts + $this->_topics + $this->_attachments_count;
	}

	/**
	 * UnapprovedATP::posts()
	 *
	 *  Query the unapproved posts
	 * 
	 * @return void
	 */
	public function posts()
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
		SELECT m.id_msg, t.id_first_msg
			FROM {db_prefix}messages AS m
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE m.approved = {int:not_approved}
				AND {query_see_board}' . (!empty($this->_approve_boards) ? ($this->_approve_boards == [0] ? '' : '
				AND m.id_board IN ({array_int:boards})') : '
				AND 1=0'),
			[
				'not_approved' => 0,
				'boards' => !empty($this->_approve_boards) && is_array($this->_approve_boards) ? $this->_approve_boards : [],
			]
		);

		$this->_posts = 0;
		$this->_topics = 0;
		while ($row =  $smcFunc['db_fetch_assoc']($request))
		{
			if ($row['id_msg'] == $row['id_first_msg'])
				$this->_topics++;
			else
				$this->_posts++;
		}
		$smcFunc['db_free_result']($request);
	}

	/**
	 * UnapprovedATP::attachments()
	 *
	 *  Query the unapproved attachments
	 * 
	 * @return void
	 */
	public function attachments()
	{
		global $smcFunc;

		$request = $smcFunc['db_query']('', '
		SELECT COUNT(a.id_attach)
			FROM {db_prefix}attachments AS a
				INNER JOIN {db_prefix}messages AS m ON (m.id_msg = a.id_msg)
				INNER JOIN {db_prefix}topics AS t ON (t.id_topic = m.id_topic)
				INNER JOIN {db_prefix}boards AS b ON (b.id_board = m.id_board)
			WHERE a.approved = {int:not_approved}
				AND {query_see_board}
				AND a.attachment_type = {int:attachment_type}' . (!empty($this->_approve_boards) ? ($this->_approve_boards == [0] ? '' : '
				AND m.id_board IN ({array_int:boards})') : '
				AND 1=0'),
			[
				'not_approved' => 0,
				'attachment_type' => 0,
				'boards' => !empty($this->_approve_boards) && is_array($this->_approve_boards) ? $this->_approve_boards : [],
			]
		);
		list ($this->_attachments_count) = $smcFunc['db_fetch_row']($request);
		$smcFunc['db_free_result']($request);
	}
}