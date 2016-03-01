<?php

final class HeraldRuleViewController extends HeraldController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needConditionsAndActions(true)
      ->executeOne();
    if (!$rule) {
      return new Aphront404Response();
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($rule->getName())
      ->setPolicyObject($rule);

    if ($rule->getIsDisabled()) {
      $header->setStatus(
        'fa-ban',
        'red',
        pht('Archived'));
    } else {
      $header->setStatus(
        'fa-check',
        'bluegrey',
        pht('Active'));
    }

    $actions = $this->buildActionView($rule);
    $properties = $this->buildPropertyView($rule);
    $details = $this->buildDetailsView($rule);

    $id = $rule->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("H{$id}");
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $rule,
      new HeraldTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = $rule->getName();

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(array(
        $details,
        $timeline,
      ))
      ->setPropertyList($properties)
      ->setActionList($actions);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildActionView(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();
    $id = $rule->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($rule);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $rule,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Rule'))
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($rule->getIsDisabled()) {
      $disable_uri = "disable/{$id}/enable/";
      $disable_icon = 'fa-check';
      $disable_name = pht('Activate Rule');
    } else {
      $disable_uri = "disable/{$id}/disable/";
      $disable_icon = 'fa-ban';
      $disable_name = pht('Archive Rule');
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Disable Rule'))
        ->setHref($this->getApplicationURI($disable_uri))
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    HeraldRule $rule) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($rule);

    $view->invokeWillRenderEvent();

    return $view;
  }

    private function buildDetailsView(
    HeraldRule $rule) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Rule Type'),
      idx(HeraldRuleTypeConfig::getRuleTypeMap(), $rule->getRuleType()));

    if ($rule->isPersonalRule()) {
      $view->addProperty(
        pht('Author'),
        $viewer->renderHandle($rule->getAuthorPHID()));
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());
    if ($adapter) {
      $view->addProperty(
        pht('Applies To'),
        idx(
          HeraldAdapter::getEnabledAdapterMap($viewer),
          $rule->getContentType()));

      if ($rule->isObjectRule()) {
        $view->addProperty(
          pht('Trigger Object'),
          $viewer->renderHandle($rule->getTriggerObjectPHID()));
      }

      $view->addSectionHeader(
        pht('Rule Description'),
        PHUIPropertyListView::ICON_SUMMARY);

      $handles = $viewer->loadHandles(HeraldAdapter::getHandlePHIDs($rule));
      $rule_text = $adapter->renderRuleAsText($rule, $handles, $viewer);
      $view->addTextContent($rule_text);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('DETAILS'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);
  }

}
