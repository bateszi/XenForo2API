<?php
namespace bateszi\XenForo2API\Pub\View;

use XF\Mvc\Reply\AbstractReply;

class Reply extends AbstractReply {

	public function __construct($renderer, $response, $template, $params) {
		$this->setJsonParams($params);
		$this->setResponseType('json');
		\XF::app()->response()->header( 'Access-Control-Allow-Origin', '*' );
	}

	public function renderJson() {
		return $this->jsonParams;
	}

	public function getTemplateName() {
		return '';
	}

	public function getParams() {
		return $this->jsonParams;
	}

}
