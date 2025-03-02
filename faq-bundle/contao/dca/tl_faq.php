<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

use Contao\Backend;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\DataContainer;
use Contao\Date;
use Contao\DC_Table;
use Contao\Environment;
use Contao\FaqCategoryModel;
use Contao\FaqModel;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\System;

System::loadLanguageFile('tl_content');

$GLOBALS['TL_DCA']['tl_faq'] = array
(
	// Config
	'config' => array
	(
		'dataContainer'               => DC_Table::class,
		'ptable'                      => 'tl_faq_category',
		'enableVersioning'            => true,
		'markAsCopy'                  => 'question',
		'onload_callback' => array
		(
			array('tl_faq', 'checkPermission'),
			array('tl_faq', 'removeMetaFields')
		),
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'pid,published' => 'index'
			)
		)
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'                    => DataContainer::MODE_PARENT,
			'fields'                  => array('sorting'),
			'panelLayout'             => 'filter;search,limit',
			'defaultSearchField'      => 'question',
			'headerFields'            => array('title', 'headline', 'jumpTo', 'tstamp', 'allowComments'),
			'child_record_callback'   => array('tl_faq', 'listQuestions'),
			'renderAsGrid'            => true
		),
		'global_operations' => array
		(
			'all' => array
			(
				'href'                => 'act=select',
				'class'               => 'header_edit_all',
				'attributes'          => 'onclick="Backend.getScrollOffset()" accesskey="e"'
			)
		)
	),

	// Palettes
	'palettes' => array
	(
		'__selector__'                => array('addImage', 'addEnclosure', 'overwriteMeta'),
		'default'                     => '{title_legend},question,alias,author;{meta_legend},pageTitle,robots,description,serpPreview;{answer_legend},answer;{image_legend},addImage;{enclosure_legend:hide},addEnclosure;{expert_legend:hide},noComments;{publish_legend},published'
	),

	// Sub-palettes
	'subpalettes' => array
	(
		'addImage'                    => 'singleSRC,fullsize,size,floating,overwriteMeta',
		'addEnclosure'                => 'enclosure',
		'overwriteMeta'               => 'alt,imageTitle,imageUrl,caption'
	),

	// Fields
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'pid' => array
		(
			'foreignKey'              => 'tl_faq_category.title',
			'sql'                     => "int(10) unsigned NOT NULL default 0",
			'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
		),
		'sorting' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'tstamp' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL default 0"
		),
		'question' => array
		(
			'search'                  => true,
			'flag'                    => DataContainer::SORT_INITIAL_LETTER_ASC,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'basicEntities'=>true, 'maxlength'=>255, 'tl_class'=>'long'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'alias' => array
		(
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'alias', 'doNotCopy'=>true, 'unique'=>true, 'maxlength'=>255, 'tl_class'=>'w50'),
			'save_callback' => array
			(
				array('tl_faq', 'generateAlias')
			),
			'sql'                     => "varchar(255) BINARY NOT NULL default ''"
		),
		'author' => array
		(
			'default'                 => static fn () => BackendUser::getInstance()->id,
			'search'                  => true,
			'filter'                  => true,
			'flag'                    => DataContainer::SORT_ASC,
			'inputType'               => 'select',
			'foreignKey'              => 'tl_user.name',
			'eval'                    => array('doNotCopy'=>true, 'chosen'=>true, 'mandatory'=>true, 'includeBlankOption'=>true, 'tl_class'=>'w50'),
			'sql'                     => "int(10) unsigned NOT NULL default 0",
			'relation'                => array('type'=>'belongsTo', 'load'=>'lazy')
		),
		'answer' => array
		(
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('mandatory'=>true, 'rte'=>'tinyMCE', 'helpwizard'=>true),
			'explanation'             => 'insertTags',
			'sql'                     => "text NULL"
		),
		'pageTitle' => array
		(
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'decodeEntities'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'robots' => array
		(
			'search'                  => true,
			'inputType'               => 'select',
			'options'                 => array('index,follow', 'index,nofollow', 'noindex,follow', 'noindex,nofollow'),
			'eval'                    => array('tl_class'=>'w50', 'includeBlankOption' => true),
			'sql'                     => "varchar(32) NOT NULL default ''"
		),
		'description' => array
		(
			'search'                  => true,
			'inputType'               => 'textarea',
			'eval'                    => array('style'=>'height:60px', 'decodeEntities'=>true, 'tl_class'=>'clr'),
			'sql'                     => "text NULL"
		),
		'serpPreview' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['serpPreview'],
			'inputType'               => 'serpPreview',
			'eval'                    => array('url_callback'=>array('tl_faq', 'getSerpUrl'), 'titleFields'=>array('pageTitle', 'question'), 'descriptionFields'=>array('description', 'answer')),
			'sql'                     => null
		),
		'addImage' => array
		(
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true),
			'sql'                     => array('type' => 'boolean', 'default' => false)
		),
		'overwriteMeta' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['overwriteMeta'],
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true, 'tl_class'=>'w50 clr'),
			'sql'                     => array('type' => 'boolean', 'default' => false)
		),
		'singleSRC' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['singleSRC'],
			'inputType'               => 'fileTree',
			'eval'                    => array('fieldType'=>'radio', 'filesOnly'=>true, 'extensions'=>'%contao.image.valid_extensions%', 'mandatory'=>true),
			'sql'                     => "binary(16) NULL"
		),
		'alt' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['alt'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'imageTitle' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['imageTitle'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'size' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['MSC']['imgSize'],
			'inputType'               => 'imageSize',
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'eval'                    => array('rgxp'=>'natural', 'includeBlankOption'=>true, 'nospace'=>true, 'helpwizard'=>true, 'tl_class'=>'w50 clr'),
			'options_callback' => static function () {
				return System::getContainer()->get('contao.image.sizes')->getOptionsForUser(BackendUser::getInstance());
			},
			'sql'                     => "varchar(64) NOT NULL default ''"
		),
		'imageUrl' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['imageUrl'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('rgxp'=>'url', 'decodeEntities'=>true, 'maxlength'=>2048, 'dcaPicker'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(2048) NOT NULL default ''"
		),
		'fullsize' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['fullsize'],
			'inputType'               => 'checkbox',
			'eval'                    => array('tl_class'=>'w50'),
			'sql'                     => array('type' => 'boolean', 'default' => false)
		),
		'caption' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['caption'],
			'search'                  => true,
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>255, 'allowHtml'=>true, 'tl_class'=>'w50'),
			'sql'                     => "varchar(255) NOT NULL default ''"
		),
		'floating' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_content']['floating'],
			'inputType'               => 'radioTable',
			'options'                 => array('above', 'left', 'right', 'below'),
			'eval'                    => array('cols'=>4, 'tl_class'=>'w50'),
			'reference'               => &$GLOBALS['TL_LANG']['MSC'],
			'sql'                     => "varchar(12) NOT NULL default 'above'"
		),
		'addEnclosure' => array
		(
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'eval'                    => array('submitOnChange'=>true),
			'sql'                     => array('type' => 'boolean', 'default' => false)
		),
		'enclosure' => array
		(
			'inputType'               => 'fileTree',
			'eval'                    => array('multiple'=>true, 'fieldType'=>'checkbox', 'filesOnly'=>true, 'isDownloads'=>true, 'extensions'=>Config::get('allowedDownload'), 'mandatory'=>true, 'isSortable'=>true),
			'sql'                     => "blob NULL"
		),
		'noComments' => array
		(
			'filter'                  => true,
			'inputType'               => 'checkbox',
			'sql'                     => array('type' => 'boolean', 'default' => false)
		),
		'published' => array
		(
			'toggle'                  => true,
			'filter'                  => true,
			'flag'                    => DataContainer::SORT_INITIAL_LETTER_DESC,
			'inputType'               => 'checkbox',
			'eval'                    => array('doNotCopy'=>true),
			'sql'                     => array('type' => 'boolean', 'default' => false)
		)
	)
);

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @internal
 */
class tl_faq extends Backend
{
	/**
	 * Import the back end user object
	 */
	public function __construct()
	{
		parent::__construct();
		$this->import(BackendUser::class, 'User');
	}

	/**
	 * Check permissions to edit table tl_faq
	 *
	 * @param DataContainer $dc
	 */
	public function checkPermission(DataContainer $dc)
	{
		$bundles = System::getContainer()->getParameter('kernel.bundles');

		// HOOK: comments extension required
		if (!isset($bundles['ContaoCommentsBundle']))
		{
			$key = array_search('allowComments', $GLOBALS['TL_DCA']['tl_faq']['list']['sorting']['headerFields'] ?? array());
			unset($GLOBALS['TL_DCA']['tl_faq']['list']['sorting']['headerFields'][$key], $GLOBALS['TL_DCA']['tl_faq']['fields']['noComments']);
		}

		if ($this->User->isAdmin)
		{
			return;
		}

		// Set the root IDs
		if (empty($this->User->faqs) || !is_array($this->User->faqs))
		{
			$root = array(0);
		}
		else
		{
			$root = $this->User->faqs;
		}

		$id = strlen(Input::get('id')) ? Input::get('id') : $dc->currentPid;

		// Check current action
		switch (Input::get('act'))
		{
			case 'paste':
			case 'select':
				// Check currentPid here (see #247)
				if (!in_array($dc->currentPid, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access FAQ category ID ' . $id . '.');
				}
				break;

			case 'create':
				if (Input::get('mode') == 1)
				{
					$objFaq = $this->Database->prepare("SELECT pid FROM tl_faq WHERE id=?")
											 ->limit(1)
											 ->execute(Input::get('pid'));

					if ($objFaq->numRows < 1)
					{
						throw new AccessDeniedException('Invalid FAQ ID ' . Input::get('pid') . '.');
					}

					$pid = $objFaq->pid;
				}
				else
				{
					$pid = Input::get('pid');
				}

				if (!in_array($pid, $root))
				{
					throw new AccessDeniedException('Not enough permissions to create FAQs in FAQ category ID ' . Input::get('pid') . '.');
				}
				break;

			case 'cut':
			case 'copy':
				if (Input::get('act') == 'cut' && Input::get('mode') == 1)
				{
					$objFaq = $this->Database->prepare("SELECT pid FROM tl_faq WHERE id=?")
											 ->limit(1)
											 ->execute(Input::get('pid'));

					if ($objFaq->numRows < 1)
					{
						throw new AccessDeniedException('Invalid FAQ ID ' . Input::get('pid') . '.');
					}

					$pid = $objFaq->pid;
				}
				else
				{
					$pid = Input::get('pid');
				}

				if (!in_array($pid, $root))
				{
					throw new AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' FAQ ID ' . $id . ' to FAQ category ID ' . $pid . '.');
				}
				// no break

			case 'edit':
			case 'show':
			case 'delete':
			case 'toggle':
				$objFaq = $this->Database->prepare("SELECT pid FROM tl_faq WHERE id=?")
										 ->limit(1)
										 ->execute($id);

				if ($objFaq->numRows < 1)
				{
					throw new AccessDeniedException('Invalid FAQ ID ' . $id . '.');
				}

				if (!in_array($objFaq->pid, $root))
				{
					throw new AccessDeniedException('Not enough permissions to ' . Input::get('act') . ' FAQ ID ' . $id . ' of FAQ category ID ' . $objFaq->pid . '.');
				}
				break;

			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'cutAll':
			case 'copyAll':
				if (!in_array($id, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access FAQ category ID ' . $id . '.');
				}

				$objFaq = $this->Database->prepare("SELECT id FROM tl_faq WHERE pid=?")
										 ->execute($id);

				$objSession = System::getContainer()->get('request_stack')->getSession();

				$session = $objSession->all();
				$session['CURRENT']['IDS'] = array_intersect((array) $session['CURRENT']['IDS'], $objFaq->fetchEach('id'));
				$objSession->replace($session);
				break;

			default:
				if (strlen(Input::get('act')))
				{
					throw new AccessDeniedException('Invalid command "' . Input::get('act') . '".');
				}

				if (!in_array($id, $root))
				{
					throw new AccessDeniedException('Not enough permissions to access FAQ category ID ' . $id . '.');
				}
				break;
		}
	}

	/**
	 * Remove the meta fields if the FAQ category does not have a jumpTo page
	 *
	 * @param DataContainer $dc
	 */
	public function removeMetaFields(DataContainer $dc)
	{
		$objFaqCategory = $this->Database
			->prepare('SELECT c.jumpTo FROM tl_faq f LEFT JOIN tl_faq_category c ON f.pid=c.id WHERE f.id=?')
			->execute($dc->id);

		if (!$objFaqCategory->jumpTo)
		{
			PaletteManipulator::create()
				->removeField(array('pageTitle', 'robots', 'description', 'serpPreview'), 'meta_legend')
				->applyToPalette('default', 'tl_faq');
		}
	}

	/**
	 * Auto-generate the FAQ alias if it has not been set yet
	 *
	 * @param mixed         $varValue
	 * @param DataContainer $dc
	 *
	 * @return mixed
	 *
	 * @throws Exception
	 */
	public function generateAlias($varValue, DataContainer $dc)
	{
		$aliasExists = function (string $alias) use ($dc): bool {
			return $this->Database->prepare("SELECT id FROM tl_faq WHERE alias=? AND id!=?")->execute($alias, $dc->id)->numRows > 0;
		};

		// Generate alias if there is none
		if (!$varValue)
		{
			$varValue = System::getContainer()->get('contao.slug')->generate($dc->activeRecord->question, FaqCategoryModel::findByPk($dc->activeRecord->pid)->jumpTo, $aliasExists);
		}
		elseif (preg_match('/^[1-9]\d*$/', $varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasNumeric'], $varValue));
		}
		elseif ($aliasExists($varValue))
		{
			throw new Exception(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
		}

		return $varValue;
	}

	/**
	 * Return the SERP URL
	 *
	 * @param FaqModel $objFaq
	 *
	 * @return string
	 */
	public function getSerpUrl(FaqModel $objFaq)
	{
		/** @var FaqCategoryModel $objCategory */
		$objCategory = $objFaq->getRelated('pid');

		if ($objCategory === null)
		{
			throw new Exception('Invalid FAQ category');
		}

		$jumpTo = $objCategory->jumpTo;

		// A jumpTo page is not mandatory for FAQ categories (see #6226) but required for the FAQ list module
		if ($jumpTo < 1)
		{
			throw new Exception('FAQ categories without redirect page cannot be used in an FAQ list');
		}

		if (!$objTarget = PageModel::findByPk($jumpTo))
		{
			return StringUtil::ampersand(Environment::get('request'));
		}

		return StringUtil::ampersand($objTarget->getAbsoluteUrl('/' . ($objFaq->alias ?: $objFaq->id)));
	}

	/**
	 * Add the type of input field
	 *
	 * @param array $arrRow
	 *
	 * @return string
	 */
	public function listQuestions($arrRow)
	{
		$key = $arrRow['published'] ? 'published' : 'unpublished';
		$date = Date::parse(Config::get('datimFormat'), $arrRow['tstamp']);

		return '
<div class="cte_type ' . $key . '">' . $date . '</div>
<div class="cte_preview limit_height' . (!Config::get('doNotCollapse') ? ' h112' : '') . '">
<h2>' . $arrRow['question'] . '</h2>
' . StringUtil::insertTagToSrc($arrRow['answer']) . '
</div>' . "\n";
	}
}
