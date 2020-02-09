<?php
/* @package Joomla
 * @copyright Copyright (C) Open Source Matters. All rights reserved.
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
 * @extension Phoca Extension
 * @copyright Copyright (C) Jan Pavelka www.phoca.cz
 * @license http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;


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
		if ($pos1 === false) {}
		else
		{
			$absU = 1;
		}

		// Test if this link is absolute https:// then do not change it
		$pos2 			= strpos($image, 'https://');
		if ($pos2 === false) {}
		else
		{
			$absU = 1;
		}


		if ($absU == 1)
		{
			$linkImg = $image;
		}
		else
		{
			$linkImg = URI::base(false).$image;

			if ($image[0] === '/')
			{
				$myURI = new \Joomla\Uri\Uri(URI::base(false));
				$myURI->setPath($image);
				$linkImg = $myURI->toString();

			}
			else
			{
				$linkImg = URI::base(false).$image;
			}

			if ($change_svg_to_png == 1)
			{
				$pathInfo 	= pathinfo($linkImg);
				if (isset($pathInfo['extension']) && $pathInfo['extension'] === 'svg')
				{
					$linkImg 	= $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.png';
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

		if($htmlspecialchars)
		{
			$name = htmlspecialchars($name, ENT_COMPAT, 'UTF-8');
			$value = strip_tags(html_entity_decode($value));
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
		if ($this->twitterEnable === 1)
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
		$app 	= $this->app;
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
		$components 		= $this->params->get('components', []);
		$component_filter 	= $this->params->get('component_filter', 1); //1 include 0 exclude

        $enable_com_content_categories 	= (int)$this->params->get('enable_com_content_categories', 0);
        $enable_com_content_featured 	= (int)$this->params->get('enable_com_content_featured', 0);

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

		if ($option === 'com_content' && $view === 'categories' && $enable_com_content_categories === 1)
		{
			$allowed		= 1;
		}
		elseif ($option === 'com_content' && $view === 'featured' && $enable_com_content_featured === 1)
		{
            $allowed		= 1;
        }
		elseif ($option === 'com_content')
        {
			if ($articleIds !== '')
			{
				$articleIdsA = explode(',', $articleIds);
				if (count($articleIdsA) > 0)
				{

					if(in_array('all', $articleIdsA))
					{
						$allowed = 1;
					}

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
			$this->twitterEnable 	= (int)$this->params->get('twitter_enable', 0);
			$twitterCard 			= $this->params->get('twitter_card', 'summary_large_image');
			$imgSet 				= 0; 			// Try to find image in content
			$menu 					= Factory::getApplication()->getMenu();
			$menuItem 				= $menu->getActive();
			$description 			= $document->description;

			if(empty($description))
			{
				$description = $this->params->get('default_description', '');
			}

			$opengraph = [
				'title' => $document->title,
				'description' => $description,
				'url' => $document->base,
				'type' => 'website',
			];

			if($menuItem !== null)
			{
				$params = $menuItem->getParams();
				$opengraph = array_merge($opengraph, [
					'title' => $params->get('phocaopengraph_title', $opengraph['title']),
					'description' => $params->get('phocaopengraph_description', $opengraph['description']),
					'type' =>  $params->get('phocaopengraph_type', $opengraph['type']),
				]);

				if($params->get('phocaopengraph_image', '') !== '')
				{
					$this->renderTag('og:image', $this->setImage($params->get('phocaopengraph_image')), $type);
					$imgSet = 1;
				}
			}


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

			$this->renderTag('og:title', $opengraph['title'], $type);
			$this->renderTag('og:description', $opengraph['description'], $type);
			$this->renderTag('og:url', $opengraph['url'], $type);
			$this->renderTag('og:type', $opengraph['type'], $type);

			if ($this->params->get('find_image_content') === 1)
			{

				$buffer = $document->getBuffer();
				$docB 	= '';
				if (isset($buffer['component']) && is_array($buffer))
				{
					foreach ($buffer['component'] as $v)
					{
						if (is_array($v))
						{
							foreach($v as $v2)
							{
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
					$imgSet = 1;
				}

				if($imagetype === 'generate')
				{
					$file = $this->getCachePath() . DIRECTORY_SEPARATOR . $this->getCacheFile();

					if(file_exists(JPATH_ROOT . DIRECTORY_SEPARATOR . $file))
					{
						$this->renderTag('og:image', $this->setImage($file), $type);
						$imgSet = 1;
					}
					else
					{

						$fileJSON = $this->getCacheFile(null);
						$path = $this->saveDataForCache($fileJSON, array_merge($opengraph));
						$this->renderTag(
							'og:image',
							$this->setImage('/index.php?' . http_build_query([
								'option' => 'com_ajax',
								'plugin' => 'phocaopengraph',
								'group' => 'system',
								'file' => $fileJSON,
								'format' => 'raw',
							])),
							$type, false);
						$imgSet = 1;

					}

				}
			}

			if($imgSet)
			{
				if((int)$this->params->get('imagefixsize', 0))
				{
					$this->renderTag('og:image:width', (int)$this->params->get('imagefixsize_width', 1200), $type);
					$this->renderTag('og:image:height', (int)$this->params->get('imagefixsize_height', 630), $type);
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

		$local = $this->getCachePath(true);
		$path = JPATH_ROOT . DIRECTORY_SEPARATOR . $local;
		$pathJSON = $path . DIRECTORY_SEPARATOR . 'json';
		$data = [];


		if(file_exists($path . DIRECTORY_SEPARATOR . $file . '.jpg'))
		{
			$this->app->redirect($local . DIRECTORY_SEPARATOR . $file . '.jpg');
		}

		//check json file
		if(!file_exists($pathJSON . DIRECTORY_SEPARATOR . $file . '.json'))
		{
			$this->showDefaultImage();
		}
		else
		{
			$data = json_decode(file_get_contents($pathJSON . DIRECTORY_SEPARATOR . $file . '.json'), JSON_OBJECT_AS_ARRAY);

			if($data === null || count($data) === 0 || !isset($data['title']))
			{
				$this->showDefaultImage();
			}

		}

		//check access on folder
		if(!is_writable($path))
		{
			$this->showDefaultImage();
		}

		//generate image
		$backgroundImage = $this->params->get('imagetype_generate_background_image');
		$backgroundTextBackground = $this->params->get('imagetype_generate_background_text_background', '#000000');
		$backgroundTextColor = $this->params->get('imagetype_generate_background_text_color', '#ffffff');
		$backgroundTextFontSize = (int)$this->params->get('imagetype_generate_background_text_fontsize', 20);
		$backgroundTextMargin = (int)$this->params->get('imagetype_generate_background_text_margin', 10);
		$backgroundTextPadding = (int)$this->params->get('imagetype_generate_background_text_padding', 10);
		$fontCustom = $this->params->get('imagetype_generate_background_text_font', '');
		$image = str_replace(DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, JPATH_ROOT . DIRECTORY_SEPARATOR . $backgroundImage);

		$img = imagecreatefromstring(file_get_contents($image));
		$colorForText = $this->hexColorAllocate($img, $backgroundTextColor);
		$txt = $data['title'];
		$font = JPATH_ROOT . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, ['media', 'plg_system_phocaopengraph', 'fonts', 'roboto.ttf']);
		if(!empty($fontCustom))
		{
			$font = JPATH_ROOT . DIRECTORY_SEPARATOR . $fontCustom;
			$font = str_replace(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $font);
		}

		$width = imagesx($img);
		$height = imagesy($img);

		$maxWidth = imagesx($img) - (($backgroundTextMargin + $backgroundTextPadding) * 2);
		$fontSizeWidthChar = $backgroundTextFontSize / 2;
		$countForWrap = (int)((imagesx($img) - (($backgroundTextMargin + $backgroundTextPadding) * 2)) / $fontSizeWidthChar);
		$text = explode("\n", wordwrap($txt, $countForWrap));
		$text_width = 0;
		$text_height = 0;

		foreach ($text as $line)
		{
			$dimensions = imagettfbbox($backgroundTextFontSize, 0, $font, $line);
			$text_width_current = max([$dimensions[2], $dimensions[4]]) - min([$dimensions[0], $dimensions[6]]);
			$text_height = $dimensions[3] - $dimensions[5];

			if($text_width < $text_width_current)
			{
				$text_width = $text_width_current;
			}
		}

		$delta_y = 0;
		if(count($text) > 1)
		{
			$delta_y = $backgroundTextFontSize * -1;
			foreach($text as $line)
			{
				$delta_y += ($dimensions[3] + $backgroundTextFontSize * 1.5);
			}
			$delta_y -= $backgroundTextFontSize * 1.5 - $backgroundTextFontSize;
		}


		$centerX = $backgroundTextPadding;
		$centerY = $height / 2;

		$centerRectX2 = $text_width > $maxWidth ? $maxWidth : $text_width;
		$centerRectY1 = $centerY - $delta_y/2 - $backgroundTextPadding;
		$centerRectY2 = $centerY + $backgroundTextPadding*2 + $delta_y/2;
		$centerRectX2 += $backgroundTextPadding *2 + $backgroundTextMargin;

		$colorForBackground = $this->hexColorAllocate($img, $backgroundTextBackground);
		imagefilledrectangle($img, $backgroundTextMargin, $centerRectY1, $centerRectX2, $centerRectY2, $colorForBackground);

		$y = $centerRectY1 + $backgroundTextPadding*2;

		$delta_y = 0;
		foreach($text as $line)
		{
			imagettftext($img, $backgroundTextFontSize, 0, $backgroundTextMargin + $backgroundTextPadding, $y + $delta_y, $colorForText, $font, $line);
			$delta_y += ($dimensions[3] + $backgroundTextFontSize * 1.5);
		}

		imagejpeg($img, $path . DIRECTORY_SEPARATOR . $file . '.jpg');

		//delete cache json
		if(file_exists($pathJSON . DIRECTORY_SEPARATOR . $file . '.json'))
		{
			File::delete($pathJSON . DIRECTORY_SEPARATOR . $file . '.json');
		}

		//redirect to image
		$this->app->redirect($local . DIRECTORY_SEPARATOR . $file . '.jpg', 302);
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
	 * If an error occurred during generation, then show the default picture
	 *
	 * @since version
	 */
	private function showDefaultImage()
	{
		$img = $this->params->get('imagetype_generate_image_for_error', '');

		if(!empty($img))
		{
			$this->app->redirect($img, 302);
		}
		else
		{
			$this->app->redirect('media/plg_system_phocaopengraph/images/default.png', 302);
		}

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
		$folder = $this->params->get('imagetype_generate_cache', 'images');
		$path = implode(DIRECTORY_SEPARATOR, [$folder, 'opengraph']);

		if($checkPath)
		{
			if(!file_exists(JPATH_ROOT . DIRECTORY_SEPARATOR . $path))
			{
				Folder::create(JPATH_ROOT . DIRECTORY_SEPARATOR . $path);
			}
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
		$file = trim(preg_replace("#\?.*?$#isu", '', $_SERVER['REQUEST_URI']), '/#');
		$file = str_replace('/', '-', $file);

		if(empty($file))
		{
			$file = 'main';
		}

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