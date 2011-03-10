<?php

Class extension_involve_your_client extends Extension
{
	private $_pageData;

	/**
     * About this extension:
     */
	public function about()
	{
		return array(
			'name' => 'Involve your client',
			'version' => '1.0',
			'release-date' => '2011-03-09',
			'author' => array(
				'name' => 'Giel Berkers',
				'website' => 'http://www.gielberkers.com',
				'email' => 'info@gielberkers.com'),
			'description' => 'Involve your clients in your Symphony projects!'
		);
	}

	/**
     * Set the delegates:
     */
	public function getSubscribedDelegates()
	{
		return array(
            array(
				'page' => '/frontend/',
				'delegate' => 'FrontendOutputPostGenerate',
				'callback' => 'injectScript'
			),
            array(
                'page' => '/frontend/',
                'delegate' => 'FrontendOutputPreGenerate',
                'callback' => 'initialize'
            )
		);
	}

    /**
     * Initialize the page. Here there are various checks for POST-data. This is because the javascript makes AJAX-calls to the page to perform specific actions
     * @param  $context     The context object provided by Symphony.
     * @return void
     */
    public function initialize($context)
    {
        $this->_pageData = $context['page']->pageData();
        if(isset($_POST['iyc_comment']))
        {
            $name    = General::sanitize($_POST['iyc_name']);
            $comment = General::sanitize($_POST['iyc_comment']);
            if(!empty($name) && !empty($comment))
            {
                $id_page = $this->_pageData['id'];
                setcookie('iyc_name', $name, time() + 7776000, '/'); // remember for three months
                $id_comment = $this->saveComment($id_page, $name, $comment);
                echo $this->generateCommentHTML($id_comment);
            }
            die();
        }
        if(isset($_POST['iyc_summary']))
        {
            $id_page = $this->_pageData['id'];
            $summary = General::sanitize($_POST['iyc_summary']);
            if(!empty($summary))
            {
                $this->saveSummary($id_page, $summary);
            }
            die();
        }
        if(isset($_POST['iyc_delete_comment']))
        {
            $this->deleteComment(intval($_POST['iyc_delete_comment']));
            die();
        }
    }

    /**
     * Inject script-tags in the headers and adds the HTML to show the iyc_box.
     * @param  $context     The context provided by Symphony.
     * @return void
     */
    public function injectScript($context)
    {
        // Add some custom code to the header:
        $context['output'] = str_replace('</head>', '
        <link rel="stylesheet" type="text/css" href="'.URL.'/extensions/involve_your_client/assets/frontend.css" />
        <script type="text/javascript" src="'.URL.'/extensions/involve_your_client/assets/frontend.js"></script>
        </head>', $context['output']);

        // Add some custom code to the body:
        $commentName = isset($_COOKIE['iyc_name']) ? $_COOKIE['iyc_name'] : '';
        $comments = $this->getComments($this->_pageData['id']);
        $commentCount = count($comments);
        $commentStr = $commentCount == 1 ? 'comment' : 'comments';

        if(Frontend::instance()->isLoggedIn())
        {
            $developer = Frontend::instance()->Author->isDeveloper();
        } else {
            $developer = false;
        }
        $editLink = $developer ? ' <a href="#" id="iyc_edit_summary">Edit</a>' : '';

        $html = '
            <div id="iyc_box">
                <a href="#" class="toggle">?!</a>
                <span class="comments">'.$commentCount.' '.$commentStr.'</span>
                <div id="iyc_content">
                    <h1>Summary'.$editLink.'</h1>
                    <p class="summary" id="iyc_summary">'.str_replace("\n", '<br />', $this->getSummary($this->_pageData['id'])).'</p>
                    <h2>Comments <a href="#" id="iyc_add_comment">Add comment</a></h2>
                    <div id="iyc_commentform">
                        <div id="iyc_loading">
                            <img src="'.URL.'/extensions/involve_your_client/assets/ajax-loader.gif" />
                        </div>
                        <form method="post" action="">
                            <label>Your name: </label>
                            <input type="text" name="name" value="'.$commentName.'" />
                            <label>Comment:</label>
                            <textarea rows="6" cols="20" name="comment"></textarea>
                            <input type="submit" value="send" />
                        </form>
                    </div>
                    <div id="iyc_comments">';
        foreach($comments as $id_comment)
        {
            // Show the comment:
            $html .= $this->generateCommentHTML($id_comment, $developer);
        }
        $html .= '  </div>
                </div>
            </div>
        </body>
        ';
        $context['output'] = str_replace('</body>', $html, $context['output']);
    }

    /**
     * Get the comments of a page
     * @param  $id_page     The ID of the page
     * @return array        An array with ID's of comments
     */
    public function getComments($id_page)
    {
        $result = Symphony::Database()->fetchCol('id', 'SELECT `id` FROM `tbl_iyc_comments` WHERE `id_page` = '.$id_page.' ORDER BY `date` DESC;');
        return $result;
    }

    /**
     * Generate the HTML of a comment
     * @param  $id_comment      The ID of the comment
     * @param bool $developer   Is the current user an author? if true, show a delete-link with the comment.
     * @return string           The HTML of the comment
     */
    public function generateCommentHTML($id_comment, $developer = false)
    {
        $result = Symphony::Database()->fetch('SELECT * FROM `tbl_iyc_comments` WHERE `id` = '.$id_comment.' ORDER BY `date` DESC;');
        $comment = $result[0];
        $html = '<div class="iyc_comment">
            <h3><strong>'.$comment['author'].'</strong> at <em>'.date('j-n-Y G:i:s', $comment['date']).'</em>:';
        if($developer) {
            $html .= '<a href="#" class="iyc_delete_comment" rel="'.$id_comment.'">Delete</a>';
        }
        $html .= '</h3>
            <p>'.str_replace("\n", '<br />', $comment['comment']).'</p>
        </div>';
        return $html;
    }

    /**
     * Get the summary of a page
     * @param  $id_page     The ID of the page
     * @return string       The summary
     */
    public function getSummary($id_page)
    {
        $summary = Symphony::Database()->fetchVar('summary', 0, 'SELECT `summary` FROM `tbl_iyc_pages` WHERE `id_page` = '.$id_page);
        return $summary;
    }

    /**
     * Save a comment and return it's ID
     * @param  $id_page     The ID of the page
     * @param  $author      The name of the author
     * @param  $comment     The comment
     * @return int          The ID of the comment
     */
    public function saveComment($id_page, $author, $comment)
    {
        Symphony::Database()->insert(array(
            'id_page' => $id_page,
            'author' => $author,
            'comment' => $comment,
            'date' => time()
        ), 'tbl_iyc_comments');
        $id = Symphony::Database()->getInsertID();
        return $id;
    }

    /**
     * Delete a comment
     * @param  $id_comment  The ID of the comment
     * @return void
     */
    public function deleteComment($id_comment)
    {
        Symphony::Database()->delete('tbl_iyc_comments', '`id` = '.$id_comment);
    }

    /**
     * Save the summary
     * @param  $id_page     The ID of the page
     * @param  $summary     The summary
     * @return void
     */
    public function saveSummary($id_page, $summary)
    {
        $total = Symphony::Database()->fetchVar('total', 0, 'SELECT COUNT(*) AS `total` FROM `tbl_iyc_pages` WHERE `id_page` = '.$id_page.';');
        if($total == 0) {
            // Insert
            Symphony::Database()->insert(array(
                'id_page' => $id_page,
                'summary' => $summary
            ), 'tbl_iyc_pages');
        } else {
            // Update
            Symphony::Database()->update(array(
                'summary' => $summary
            ), 'tbl_iyc_pages', '`id_page` = '.$id_page);
        }
    }

    /**
     * Installation
     * @return void
     */
    public function install()
    {
        // Pages:
        Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_iyc_pages` (
            `id` INT(11) unsigned NOT NULL auto_increment,
            `id_page` INT(255) unsigned NOT NULL,
            `summary` MEDIUMTEXT NOT NULL,
        PRIMARY KEY (`id`),
        KEY `id_page` (`id_page`)
        );");

        // Comments:
        Symphony::Database()->query("CREATE TABLE IF NOT EXISTS `tbl_iyc_comments` (
            `id` INT(11) unsigned NOT NULL auto_increment,
            `id_page` INT(255) unsigned NOT NULL,
            `author` TINYTEXT NOT NULL,
            `date` TINYTEXT NOT NULL,
            `comment` MEDIUMTEXT NOT NULL,
        PRIMARY KEY (`id`),
        KEY `id_page` (`id_page`)
        );");

    }

    /**
     * Uninstallation
     */
    public function uninstall()
    {
        // Drop all the tables:
        Symphony::Database()->query("DROP TABLE `tbl_iyc_pages`");
        Symphony::Database()->query("DROP TABLE `tbl_iyc_comments`");
    }

}