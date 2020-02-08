<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

defined( '_JEXEC' ) or die( 'Restricted access' );

class plgSystemPhocaOpenGraph extends CMSPlugin
{


	/**
	 * Application object
	 *
	 * @var    CMSApplication
	 * @since  1.0.0
	 */
	protected $app;

	/**
	 * Database object
	 *
	 * @var    DatabaseDriver
	 * @since  1.0.0
	 */
	protected $db;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * @var int
	 * @since version
	 */
	public $twitterEnable 	= 0;


	private function setImage($image)
	{

		$change_svg_to_png 		= $this->params->get('change_svg_to_png', 0);
		$linkImg 				= $image;

		$absU = 0;
		// Test if this link is absolute http:// then do not change it
		$pos1 			= strpos($image, 'http://');
		if ($pos1 === false) {
		} else {
			$absU = 1;
		}

		// Test if this link is absolute https:// then do not change it
		$pos2 			= strpos($image, 'https://');
		if ($pos2 === false) {
		} else {
			$absU = 1;
		}


		if ($absU == 1)
		{
			$linkImg = $image;
		}
		else
		{
			$linkImg = JURI::base(false).$image;

			if ($image[0] === '/')
			{
				$myURI = new \Joomla\Uri\Uri(JURI::base(false));
				$myURI->setPath($image);
				$linkImg = $myURI->toString();

			}
			else
			{
				$linkImg = JURI::base(false).$image;
			}

			if ($change_svg_to_png == 1)
			{
				$pathInfo 	= pathinfo($linkImg);
				if (isset($pathInfo['extension']) && $pathInfo['extension'] === 'svg') {
					$linkImg 	= $pathInfo['dirname'] .'/'. $pathInfo['filename'] . '.png';
				}
			}

		}

		return $linkImg;
	}


	/**
	 * Adds forms for override
	 *
	 * @param  JForm $form The form to be altered.
	 * @param  mixed $data The associated data for the form.
	 *
	 * @return  boolean
	 *
	 * @since   1.0
	 */
	public function onContentPrepareForm($form, $data)
	{
		$app = Factory::getApplication();
		$component = $app->input->get('option');
		$layout = $app->input->get('layout');
		if ($app->isClient('administrator') && $component === 'com_menus' && $layout === 'edit')
		{

			Form::addFormPath(__DIR__);
			$form->loadFile('formoverridemain', true);

			//if((int)$this->params('twitter_enable', 0))
			//{
				$form->loadFile('formoverridetweets', true);
			//}


		}

		return true;
	}


	/**
	 * @param $name
	 * @param $value
	 * @param int $type
	 *
	 *
	 * @since version
	 */
	private function renderTag($name, $value, $type = 1, $htmlspecialchars = true)
	{

		$document 	= Factory::getDocument();
		$docType	= $document->getType();

		if ($docType === 'pdf' || $docType === 'raw' || $docType === 'json')
		{
			return;
		}

		// Encoded html tags can still be rendered, decode and strip tags first.
		$value                  = strip_tags(html_entity_decode($value));


		if($htmlspecialchars)
		{
			$name = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
			$value = htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
		}

		// OG

		if ($type === 1)
		{
			$document->setMetadata($name, $value);
		}
		else
		{
			$document->addCustomTag('<meta property="'. $name.'" content="' . $value . '" />');
		}

		// Tweet with cards
		if ($this->twitterEnable == 1)
		{
			if ($name === 'og:title')
			{
				$document->setMetadata('twitter:title', $value);
			}
			if ($name === 'og:description')
			{
				$document->setMetadata('twitter:description', $value);
			}
			if ($name === 'og:image')
			{
				$document->setMetadata('twitter:image', $value);
			}
		}
	}

	public function onBeforeRender() {
		$app 	= Factory::getApplication();
		$option	= $app->input->get('option');
		$view	= $app->input->get('view');
		$format = $app->input->get('format');

		if ($format === 'feed' || $format === 'pdf' || $format === 'json' || $format === 'raw')
		{
			return true;
		}


		if ($app->getName() !== 'site')
		{
			return;
		}

		// Component included
		$components 		= $this->params->get('components', array());
		$component_filter 	= $this->params->get('component_filter', 1);//1 include 0 exclude

        $enable_com_content_categories 	= $this->params->get('enable_com_content_categories', 0);
        $enable_com_content_featured 	= $this->params->get('enable_com_content_featured', 0);

		if (!empty($components))
		{
			$cA	= explode(',', $components);

			if (empty($cA))
			{
				// No component set, ignore this rule
			}
			else
			{
				if ($component_filter == 0)
				{
					// All except the selected
					if (in_array($option, $cA))
					{
						return false;
					}
				}
				else
					{
					// All selected
					if (!in_array($option, $cA))
					{
						return false;
					}
				}
			}
		}



		// Articles allowed
		$allowed		= 0;
		$articleIds 		= $this->params->get('enable_article', '');

		if ($option === 'com_content' && $view === 'categories' && $enable_com_content_categories == 1)
		{
			$allowed		= 1;
		}
		elseif ($option === 'com_content' && $view = 'featured' && $enable_com_content_featured == 1)
		{
            $allowed		= 1;
        }
		elseif ($option === 'com_content')
        {
			if ($articleIds !== '')
			{
				$articleIdsA =  explode(',', $articleIds);
				if (!empty($articleIdsA)) {
					$articleId	= $app->input->get('id', 0, 'int');
					foreach ($articleIdsA as $k => $v)
					{
						if ($option === 'com_content' && (int)$articleId > 0 && (int)$articleId === (int)$v)
						{
							//$articleAllowed = (int)$articleId;
							$allowed = (int)$articleId;
							break;
						}
					}
				}
			}
		}
		elseif ($option !== 'com_content')
		{
			$allowed		= 1;
		}

		//com_phocadownload
		//com_phocadocumentation
		//com_phocainclude

		if ($allowed > 0)
		{

			$document 				= Factory::getDocument();
			$config 				= Factory::getConfig();
			$type					= $this->params->get('render_type', 1);
			$this->twitterEnable 	= $this->params->get('twitter_enable', 0);
			$twitterCard 			= $this->params->get('twitter_card', 'summary_large_image');
			$opengraph = [
				'title' => $document->title,
				'description' => $document->description,
				'url' => $document->base,
				'type' => $type,
			];

			if ($this->twitterEnable == 1)
			{
                $this->renderTag('twitter:card', $twitterCard, 1);

                if ($this->params->get('twitter_site', '') !== '')
                {
                    $this->renderTag('twitter:site', $this->params->get('twitter_site', ''), 1);
                }

                if ($this->params->get('twitter_site', '') !== '')
                {
                    $this->renderTag('twitter:creator', $this->params->get('twitter_creator', ''), 1);
                }
            }


			// Site Name
			if ($this->params->get('site_name', '') !== '')
			{
				$this->renderTag('og:site_name', $this->params->get('site_name', ''), $type);
			}
			else
			{
				$this->renderTag('og:site_name', $config->get('sitename'), $type);
			}

			$this->renderTag('og:title', $document->title, $type);
			$this->renderTag('og:description', $document->description, $type);
			$this->renderTag('og:url', $document->base, $type);
			$this->renderTag('og:type', 'website', $type);


			// Try to find image in content
			$imgSet = 0;
			if ($this->params->get('find_image_content') === 1)
			{

				$buffer = $document->getBuffer();
				$docB 	= '';
				if (isset($buffer['component']) && is_array($buffer))
				{
					foreach ($buffer['component'] as $v) {
						if (is_array($v))
						{
							foreach($v as $v2) {
								$docB .= (string)$v2;
							}
						}
						else
						{
							$docB .= (string)$v;
						}
					}
				}
				else
				{
					$docB .=	(string)$buffer;
				}

				preg_match('/< *img[^>]*src *= *["\']?([^"\']*)/i', $docB, $src);
				if (isset($src[1]) && $src[1] !== '')
				{
					$this->renderTag('og:image', $this->setImage($src[1]), $type);
					$imgSet = 1;
				}

			}

			if ($imgSet === 0)
			{
				//($this->params->get('image')
				$imagetype = $this->params->get('imagetype', 'image');

				if($imagetype === 'image')
				{
					$this->renderTag('og:image', $this->setImage($this->params->get('image')), $type);
				}

				if($imagetype === 'generate')
				{
					$file = $this->getCacheFile();

					if(file_exists(JPATH_ROOT . DIRECTORY_SEPARATOR . $file))
					{
						$this->renderTag('og:image', $this->setImage($file), $type);
					}
					else
					{

						$fileJSON = $this->getCacheFile(null);
						$path = $this->saveDataForCache($fileJSON, array_merge($opengraph));
						$this->renderTag(
							'og:image',
							'/index.php?' . http_build_query([
								'option' => 'com_ajax',
								'plugin' => 'phocaopengraph',
								'group' => 'system',
								'file' => $fileJSON,
								'format' => 'raw',
							]),
							$type, false);

					}

				}
			}

		}

	}

	public function onAjaxPhocaopengraph()
	{
		$file = $this->app->input->get('file', '');

		if(empty($file))
		{
			$this->showDefaultImage();
		}

		$path = JPATH_ROOT . DIRECTORY_SEPARATOR . $this->getCachePath(true);
		$pathJSON = $path . DIRECTORY_SEPARATOR . 'json';
		$data = [];


		//check json file
		if(!file_exists($pathJSON . DIRECTORY_SEPARATOR . $file . '.json'))
		{
			$this->showDefaultImage();
		}
		else
		{
			$data = json_decode(file_get_contents($pathJSON . DIRECTORY_SEPARATOR . $file . '.json'), JSON_OBJECT_AS_ARRAY);

			if($data === null || count($data) === 0)
			{
				$this->showDefaultImage();
			}

		}

		//generate image
		$backgroundImage = $this->params->get('imagetype_generate_background_image');
		$backgroundTextBackground = $this->params->get('imagetype_generate_background_text_background', '#dddddd');
		$backgroundTextColor = $this->params->get('imagetype_generate_background_text_color', '#000000');
		$backgroundTextFontSize = (int)$this->params->get('imagetype_generate_background_text_fontsize', 20);
		$backgroundTextMargin = (int)$this->params->get('imagetype_generate_background_text_margin', 60);
		$backgroundTextPadding = (int)$this->params->get('imagetype_generate_background_text_padding', 30);
		$image = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, JPATH_ROOT . DIRECTORY_SEPARATOR . $backgroundImage);

		$img = imagecreatefromjpeg($image);
		$colorForText = $this->hexColorAllocate($img, $backgroundTextColor);
		$txt = $data['title'];
		$txt = '"Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum';
		$font = __DIR__ . DIRECTORY_SEPARATOR . '/roboto.ttf';


		$width = imagesx($img);
		$height = imagesy($img);

		$maxWidth = imagesx($img) - (($backgroundTextMargin + $backgroundTextPadding) * 2);
		$fontSizeWidthChar = $backgroundTextFontSize / 1.5;
		$countForWrap = (int)((imagesx($img) - (($backgroundTextMargin + $backgroundTextPadding) * 2)) / $fontSizeWidthChar);
		$dimensions = imagettfbbox($backgroundTextFontSize, 0, $font, $txt);
		$text = explode("\n", wordwrap($txt, $countForWrap));
		$text_width = max([$dimensions[2], $dimensions[4]]) - min([$dimensions[0], $dimensions[6]]);
		$text_height = $dimensions[3] - $dimensions[5];


		$delta_y = 0;
		if(count($text) > 1)
		{
			$delta_y = $backgroundTextFontSize * -1;
			foreach($text as $line)
			{
				$delta_y =  $delta_y + ($dimensions[3] + $backgroundTextFontSize);
			}
			$delta_y += $delta_y;
		}


		$centerX = $backgroundTextPadding;
		$centerY = CEIL(($height - $text_height - $delta_y/2) / 2);
		$centerY = $centerY < 0 ? 0 : $centerY;


		$centerRectX2 = $text_width > $maxWidth ? ($dimensions[2] / (count($text) > 1 ? (count($text) - 1) : 1)) : $text_width;
		$centerRectY1 = $centerY - ($text_height) - $backgroundTextPadding;
		$centerRectY2 = $centerY;
		$centerRectY2 += $backgroundTextPadding + $delta_y/2;
		$centerRectX2 += $backgroundTextPadding *2 + $backgroundTextMargin;

		$colorForBackground = $this->hexColorAllocate($img, $backgroundTextBackground);
		imagefilledrectangle($img, $backgroundTextMargin, $centerRectY1, $centerRectX2, $centerRectY2, $colorForBackground);

		$y = $centerY;
		$delta_y = 0;
		foreach($text as $line)
		{
			imagettftext($img, $backgroundTextFontSize, 0, $backgroundTextMargin + $backgroundTextPadding, $y + $delta_y, $colorForText, $font, $line);
			$delta_y =  $delta_y + ($dimensions[3] + $backgroundTextFontSize);
		}

		header('Content-type: image/jpeg');
		imagejpeg($img);

		die();

		//delete cache json
		//File::delete($pathJSON . DIRECTORY_SEPARATOR . $fileJSON);


		//redirect to image

	}


	/**
	 * @param $im
	 * @param $hex
	 *
	 * @return false|int
	 *
	 * @since version
	 */
	private function hexColorAllocate($im, $hex)
	{
		$hex = ltrim($hex,'#');
		$a = hexdec(substr($hex,0,2));
		$b = hexdec(substr($hex,2,2));
		$c = hexdec(substr($hex,4,2));
		return imagecolorallocate($im, $a, $b, $c);
	}


	/**
	 *
	 *
	 * @since version
	 */
	private function showDefaultImage()
	{
		die();
	}


	/**
	 * @param bool $checkPath
	 *
	 * @return string
	 *
	 * @since version
	 */
	private function getCachePath($checkPath = false)
	{
		$path = implode(DIRECTORY_SEPARATOR, ['cache', 'opengraph']);

		if(!file_exists(JPATH_ROOT . DIRECTORY_SEPARATOR . $path))
		{
			Folder::create(JPATH_ROOT . DIRECTORY_SEPARATOR . $path);
		}

		return $path;
	}


	/**
	 * @param string $exs
	 *
	 * @return string
	 *
	 * @since version
	 */
	private function getCacheFile($exs = 'jpg')
	{
		$file = trim(preg_replace("#\?.*?$#isu", '', $_SERVER['REQUEST_URI']), '/');
		$file = str_replace('/', '-', $file);

		if($exs === null)
		{
			return $file;
		}
		else
		{
			return $file . '.' . $exs;
		}
	}


	/**
	 * @param string $file
	 * @param array $data
	 *
	 *
	 * @since version
	 */
	private function saveDataForCache($file = '', $data = [])
	{
		$path = $this->getCachePath(true) . DIRECTORY_SEPARATOR . 'json';
		$pathFull = JPATH_ROOT . DIRECTORY_SEPARATOR . $path;

		if(!file_exists($pathFull))
		{
			Folder::create($pathFull);
		}

		file_put_contents($pathFull . DIRECTORY_SEPARATOR . $file . '.json', json_encode($data));
	}

}