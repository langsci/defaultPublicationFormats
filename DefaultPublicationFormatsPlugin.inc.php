<?php

/**
 * @file plugins/generic/defaultPublicationFormats/DefaultPublicationFormatsPlugin.inc.php
 *
 * Copyright (c) 2022 Language Science Press
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DefaultPublicationFormatsPlugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class DefaultPublicationFormatsPlugin extends GenericPlugin
{
    /**
     * Register the plugin.
     * @param $category string
     * @param $path string
     * @param $mainContextId strinf
     */
    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (!Config::getVar('general', 'installed') || defined('RUNNING_UPGRADE')) return true;
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                HookRegistry::register('Template::Workflow::Publication', array($this, 'addDefaultPublicationFormats'));
            }
			# add a filed to identify formats created by this pugin
			HookRegistry::register('Schema::get::publication', function($hookName, $args) {
				$schema = $args[0];
				$schema->properties->defaultPubFormatsCreated = (object) [
					'type' => 'string',
					"default" => ''
				];
				return false;
			});
            return true;
        }
        return false;
    }

	/**
	 * Add default publication formats
	 * @param $hookName string
	 * @param $args array
	 */
	function addDefaultPublicationFormats($hookName, $args){
		$output =& $args[2];
		$request = $this->getRequest();
		$submission = Services::get('submission')->get($request->getRequestedArgs()[0]);
		$publication = $submission->getCurrentPublication();

		// define default publication formats
		$defaultFormatsMap = array_reverse([
			'PDF' => 'DA',
			'Bibliography' => 'DA',
			'Hardcover' => 'BB',
			'Buy from Amazon.de' => 'BC',
			'Buy from Amazon.co.uk' => 'BC',
			'Buy from Amazon.com' => 'BC',
			'Collaborative reading on Paperhive' => 'DA'
		]);

		// get current publication formats
		$publicationFormats = $publication->_data['publicationFormats'];
		$currentPublicationFormatNames = array_map(function($v) {
			return $v->getLocalizedName();
		},$publicationFormats);

		// get missing publication formats
		$missingPublicationFormatNames = array_diff(array_keys($defaultFormatsMap), $currentPublicationFormatNames);

		# delete all publication formats for this publication !!! only for debugging ""
		if (false) {
			foreach ($publicationFormats as $pf) {
				Services::get('publicationFormat')->deleteFormat($pf, $submission, $request->getContext());
			}
		}

		// create missing publication formats
		foreach ($missingPublicationFormatNames as $missingPublicationFormatName) {
			$publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO'); /* @var $publicationFormatDao PublicationFormatDAO */
			
			$publicationFormat = $publicationFormatDao->newDataObject();
			$publicationFormat->setData('publicationId', $publication->getId());
			
			$publicationFormat->setName([$publication->getData('locale') => $missingPublicationFormatName]);
			$publicationFormat->setEntryKey($defaultFormatsMap[$missingPublicationFormatName]);
			if ($missingPublicationFormatName == 'Hardcover') {
				$publicationFormat->setPhysicalFormat(true);
				$publicationFormat->setWidth(180);
				$publicationFormat->setHeight(245);
			} else {
				$publicationFormat->setPhysicalFormat(false);
			}
			
			$representationId = $publicationFormatDao->insertObject($publicationFormat);

			// log the creation of the format.
			import('lib.pkp.classes.log.SubmissionLog');
			import('classes.log.SubmissionEventLogEntry');
			SubmissionLog::logEvent(Application::get()->getRequest(), $submission, SUBMISSION_LOG_PUBLICATION_FORMAT_CREATE, 'submission.event.publicationFormatCreated', array('formatName' => $publicationFormat->getLocalizedName()));
		}
		# store publication format anmes created by this plugin (can be used to removed them again in required)
		if ($missingPublicationFormatNames) {
			$publication = Services::get('publication')->edit($publication, ['defaultPubFormatsCreated' => json_encode($missingPublicationFormatNames)], $request);
		}
		  
		return false;
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.generic.defaultPublicationFormats.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.defaultPublicationFormats.description');
	}	
}
?>