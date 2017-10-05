<?php

final class DifferentialReviewersAddRoundRobinBlockingReviewersHeraldAction
  extends DifferentialReviewersHeraldAction {

  const ACTIONCONST = 'differential.round.robin.reviewers.add';

  public function getHeraldActionName() {
    return pht('Add a blocking reviewer in the Round-Robin way');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type != HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $rule_ID = $effect->getRule()->getID();
    $proj_phids = $effect->getTarget();
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/../libphutil/src/__phutil_library_init__.php';
    $api_token = "xxxxxxxxxxxxxxxxxx"; #this is a Phabricator api token you need to put here
    $api_parameters = array(
      'phids' => array(
        $proj_phids[0],
      ),
    );
    $phabricator_base_uri = PhabricatorEnv::getEnvConfig('phabricator.base-uri');
    $client = new ConduitClient($phabricator_base_uri);
    $client->setConduitToken($api_token);
    $result = $client->callMethodSynchronous('project.query', $api_parameters);
    $phid = $this->getOneMember($rule_ID, $proj_phids[0], $result["data"][$proj_phids[0]]["members"]);
    $new_phids[] = $phid;
    return $this->applyReviewers($new_phids, $is_blocking = true);
  }

  public function getOneMember($rule_ID, $project, $member_phids_latest){
    $cache = new PhabricatorKeyValueDatabaseCache();
    $cache = new PhutilKeyValueCacheProfiler($cache);
    $cache->setProfiler(PhutilServiceProfiler::getInstance());
    $keys = array();
    $key = $project . $rule_ID . '_rr_string';
    $keys[] = $key;
    $caches = $cache->getKeys($keys);
    $member_phids = $member_phids_latest;
    if (isset($caches[$key])) {
        $member_phids_string = $caches[$key];
        $member_phids = explode(',', $member_phids_string);

        #remove the members who are in the caches but not in the latest member list.
        $not_in_latest_phids = array_diff($member_phids, $member_phids_latest);
        if ($not_in_latest_phids){
            $member_phids = array_diff($member_phids, $not_in_latest_phids);
        }
        #add the members who are not in the caches but in the latest member list.
        $new_member_from_latest_phids = array_diff($member_phids_latest, $member_phids);
        if ($new_member_from_latest_phids){
            $member_phids = array_merge($new_member_from_latest_phids, $member_phids);
        }
       } 
    $first_member = array_shift($member_phids);
    $member_phids[] = $first_member;
    if ($first_member == $this->getAuthor()) {
        $first_member = array_shift($member_phids);
        $member_phids[] = $first_member;
    }
    $write_data = array();
    $write_data[$key] = implode(",",$member_phids);
    if ($write_data) {
      $cache->setKeys($write_data);
    }
    return $first_member;
  }

  public function getAuthor(){
    $adapter = $this->getAdapter();
    $object = $adapter->getObject();
    return $object->getAuthorPHID();
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorMetaMTAMailableDatasource();
  }

  public function renderActionDescription($value) {
    return pht('Add a blocking reviewer in the Round-Robin way: %s.', $this->renderHandleList($value));
  }

}
