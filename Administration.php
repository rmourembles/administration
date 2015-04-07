<?php

/*
 * RESTo
 * 
 * RESTo - REstful Semantic search Tool for geOspatial 
 * 
 * Copyright 2014 Jérôme Gasperi <https://github.com/jjrom>
 * 
 * jerome[dot]gasperi[at]gmail[dot]com
 * 
 * This software is governed by the CeCILL-B license under French law and
 * abiding by the rules of distribution of free software.  You can  use,
 * modify and/ or redistribute the software under the terms of the CeCILL-B
 * license as circulated by CEA, CNRS and INRIA at the following URL
 * "http://www.cecill.info".
 *
 * As a counterpart to the access to the source code and  rights to copy,
 * modify and redistribute granted by the license, users are provided only
 * with a limited warranty  and the software's author,  the holder of the
 * economic rights,  and the successive licensors  have only  limited
 * liability.
 *
 * In this respect, the user's attention is drawn to the risks associated
 * with loading,  using,  modifying and/or developing or reproducing the
 * software by the user in light of its specific status of free software,
 * that may mean  that it is complicated to manipulate,  and  that  also
 * therefore means  that it is reserved for developers  and  experienced
 * professionals having in-depth computer knowledge. Users are therefore
 * encouraged to load and test the software's suitability as regards their
 * requirements in conditions enabling the security of their systems and/or
 * data to be ensured and,  more generally, to use and operate it in the
 * same conditions as regards security.
 *
 * The fact that you are presently reading this means that you have had
 * knowledge of the CeCILL-B license and that you accept its terms.
 * 
 */

/**
 * RESTo v2 Administration module
 * 
 * Authors :
 * 
 *      jerome[dot]gasperi[at]gmail[dot]com
 *      remi[dot]mourembles[at]capgemini[dot]com
 * 
 * This module provides html hmi to administrate RESTo
 * 
 * ** Administration **
 * 
 * 
 *    |          Resource                                                             |     Description
 *    |_______________________________________________________________________________|______________________________________
 *    |  POST    administration/users/{userid}/rights                                 |  Add new rights for {userid}
 *    |  GET     administration/users/{userid}/rights                                 |  Get detailed rights for {userid}
 *    |  POST    administration/users/{userid}/rights/{collection}                    |  Add new rights for {userid}
 *    |  POST    administration/users/{userid}/rights/{collection}/{feature}          |  Add new rights for {userid}
 *    |  POST    administration/users/{userid}/activate                               |  Activate {userid}
 *    |  POST    administration/users/{userid}/deactivate                             |  Deactivate {userid}
 *    |  GET     administration/rights                                                |  Get default rights by collections for each groups
 *    |  POST    administration/rights                                                |  Update rights
 *    |  GET     administration/stats/users                                           |  Get stats about users
 *    |  GET     administration/stats/collections                                     |  Get stats about collections
 * 
 */
class Administration extends RestoModule {
    /*
     * Resto context
     */

    public $context;

    /*
     * Current user (only set for administration on a single user)
     */
    public $user = null;

    /*
     * segments
     */
    public $segments;

    /**
     * Constructor
     * 
     * @param RestoContext $context
     * @param array $options : array of module parameters
     */
    public function __construct($context, $user, $options = array()) {

        parent::__construct($context, $options);

        // Set user
        $this->user = $user;

        // Set context
        $this->context = $context;
    }

    /**
     * Run 
     * 
     * @param array $segments
     * @throws Exception
     */
    public function run($segments) {

        if ($this->user->profile['groupname'] !== 'admin') {
            /*
             * Only administrators can access to administration
             */

            RestoLogUtil::httpError(401);
        }

        if ($this->context->method === 'POST' && $this->context->outputFormat !== 'json') {
            /*
             * Only JSON can be posted
             */
            RestoLogUtil::httpError(404);
        }

        $this->segments = $segments;
        $method = $this->context->method;

        /*
         * Switch on HTTP methods
         */
        switch ($method) {
            case 'GET':
                return $this->processGET();
            case 'POST':
                return $this->processPOST();
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process on HTTP method POST on /administration
     * 
     * @throws Exception
     */
    private function processPOST() {



        /*
         * Can't post file on /administration
         */
        if (!isset($this->segments[0])) {
            RestoLogUtil::httpError(404);
        }
        /*
         * Switch on url segments
         */ else {
            switch ($this->segments[0]) {
                case 'users':
                    return $this->processPostUsers();
                case 'collections':
                    return $this->processPostCollections();
                default:
                    RestoLogUtil::httpError(404);
            }
        }
    }

    /**
     * Process on HTTP method GET on /administration
     * 
     * @throws Exception
     */
    private function processGET() {


        switch ($this->segments[0]) {
            case 'users':
                return $this->processGetUsers();
            case 'collections':
                return $this->processGetCollections();
            case 'stats':
                return $this->processStatistics();
            default:
                RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process when GET on /administration/collections
     * 
     * @throws Exception
     */
    private function processGetCollections() {



        /*
         * Get on /administration/collections
         */
        if (isset($this->segments[1])) {
            RestoLogUtil::httpError(404);
        } else {
            $rights = array();
            $this->groups = $this->context->dbDriver->get(RestoDatabaseDriver::GROUPS);
            $this->collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS);
            foreach ($this->collections as $collection) {

                $group = 'default';
                $item = array();
                $item['name'] = $collection['collection'];
                $item['group'] = $group;

                $restoRights = new RestoRights($group, $group, $this->context);
                $item['rights'] = $restoRights->getRights($collection['collection']);
                $rights[$collection['collection']] = $item;
            }

            return $this->to($rights);
        }
    }

    /**
     * Process when POST on /administration/collections
     * 
     * @throws Exception
     */
    private function processPostCollections() {



        if (isset($this->segments[1])) {
            RestoLogUtil::httpError(404);
        }
        /*
         * Update rights
         */ else {
            return $this->updateRights();
        }
    }

    /**
     * Process get on /administration/users/{userid}
     * 
     * @throws Exception
     */
    private function processGetUser() {



        if ($this->segments[2] == 'history') {

            /**
             * Process get on /administration/users/{userid}/history
             * 
             * Return the history for user associated to {userid}
             */
            $this->startIndex = 0;
            $this->numberOfResults = 12;
            $this->keyword = null;
            $this->collectionFilter = null;
            $this->service = null;
            $this->orderBy = null;
            $this->ascordesc = null;
            $this->method = null;
            /*
             * Get request params
             */
            if (filter_input(INPUT_GET, 'startIndex')) {
                $this->startIndex = htmlspecialchars(filter_input(INPUT_GET, 'startIndex'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'numberOfResults')) {
                $this->numberOfResults = htmlspecialchars(filter_input(INPUT_GET, 'numberOfResults'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'collection')) {
                $this->collectionFilter = htmlspecialchars(filter_input(INPUT_GET, 'collection'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'service')) {
                $this->service = htmlspecialchars(filter_input(INPUT_GET, 'service'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'method')) {
                $this->method = htmlspecialchars(filter_input(INPUT_GET, 'method'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'orderBy')) {
                $this->orderBy = htmlspecialchars(filter_input(INPUT_GET, 'orderBy'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'ascordesc')) {
                $this->ascordesc = htmlspecialchars(filter_input(INPUT_GET, 'ascordesc'), ENT_QUOTES);
            }

            $options = array(
                'orderBy' => $this->orderBy,
                'ascOrDesc' => $this->ascordesc,
                'collectionName' => $this->collectionFilter,
                'service' => $this->service,
                'method' => $this->method,
                'startIndex' => $this->startIndex,
                'numberOfResults' => $this->numberOfResults
            );

            $this->historyList = $this->getHistory($this->segments[1], $options);

            return $this->to($this->historyList);
        } else if ($this->segments[2] == 'rights') {

            /*
             * Process get on /administration/users/{userid}/rights
             * 
             * Get rights on all collections and features for user associated to {userid}
             * 
             */

            $user = new RestoUser($this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $this->segments[1])), $this->context);

            $rights = array();
            $collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS);

            $fullRights = $user->getFullRights();

            foreach ($collections as $collection) {

                $collectionName = $collection['collection'];

                $rights[$collectionName] = $user->getRights($collectionName);
                if (isset($fullRights[$collectionName])) {
                    $rights[$collectionName]['features'] = $fullRights[$collectionName]['features'];
                }
            }

            return RestoLogUtil::success('Rights for ' . $user->profile['userid'], array(
                        'userid' => $user->profile['userid'],
                        'groupname' => $user->profile['groupname'],
                        'rights' => $rights
            ));
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    /**
     * Process when GET on /administration/users
     * 
     * @throws Exception
     */
    private function processGetUsers() {



        /*
         * Get user creation MMI
         */
        if (isset($this->segments[1])) {
            if ($this->segments[1] == 'history') {

                $this->startIndex = 0;
                $this->numberOfResults = 12;
                $this->keyword = null;
                $this->collectionFilter = null;
                $this->service = null;
                $this->orderBy = null;
                $this->ascordesc = null;
                $this->method = null;
                if (filter_input(INPUT_GET, 'startIndex')) {
                    $this->startIndex = htmlspecialchars(filter_input(INPUT_GET, 'startIndex'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'numberOfResults')) {
                    $this->numberOfResults = htmlspecialchars(filter_input(INPUT_GET, 'numberOfResults'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'collection')) {
                    $this->collectionFilter = htmlspecialchars(filter_input(INPUT_GET, 'collection'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'service')) {
                    $this->service = htmlspecialchars(filter_input(INPUT_GET, 'service'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'method')) {
                    $this->method = htmlspecialchars(filter_input(INPUT_GET, 'method'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'orderBy')) {
                    $this->orderBy = htmlspecialchars(filter_input(INPUT_GET, 'orderBy'), ENT_QUOTES);
                }
                if (filter_input(INPUT_GET, 'ascordesc')) {
                    $this->ascordesc = htmlspecialchars(filter_input(INPUT_GET, 'ascordesc'), ENT_QUOTES);
                }

                $options = array(
                    'orderBy' => $this->orderBy,
                    'ascOrDesc' => $this->ascordesc,
                    'collection' => $this->collectionFilter,
                    'service' => $this->service,
                    'method' => $this->method,
                    'startIndex' => $this->startIndex,
                    'numberOfResults' => $this->numberOfResults
                );

                $this->historyList = $this->getHistory(null, $options);

                return $this->to($this->historyList);
            } else {
                return $this->processGetUser();
            }
        } else {
            /*
             * Users list MMI
             */
            $this->min = 0;
            $this->number = 50;
            $this->keyword = null;
            if (filter_input(INPUT_GET, 'min')) {
                $this->min = htmlspecialchars(filter_input(INPUT_GET, 'min'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'number')) {
                $this->number = htmlspecialchars(filter_input(INPUT_GET, 'number'), ENT_QUOTES);
            }
            if (filter_input(INPUT_GET, 'keyword')) {
                $this->keyword = htmlspecialchars(filter_input(INPUT_GET, 'keyword'), ENT_QUOTES);
                $this->global_search_val = htmlspecialchars(filter_input(INPUT_GET, 'keyword'), ENT_QUOTES);
            } else {
                $this->keyword = null;
                $this->global_search_val = $this->context->dictionary->translate('_menu_globalsearch');
            }
            $this->usersProfiles = $this->getUsersProfiles($this->keyword, $this->min, $this->number);

            return $this->to($this->usersProfiles);
        }
    }

    /**
     * Process when POST on /administration/users
     * 
     * @throws Exception
     */
    private function processPostUsers() {



        if (isset($this->segments[1])) {
            return $this->processPostUser();
        } else {
            /*
             * Insert user
             */
            return $this->insertUser();
        }
    }

    /**
     * Process when post on /administration/users/{userid}
     * 
     * @throws Exception
     */
    private function processPostUser() {



        if (isset($this->segments[2])) {

            /*
             * Activate user
             */
            if ($this->segments[2] == 'activate') {
                return $this->activate();
            }
            /*
             * Deactivate user
             */ else if ($this->segments[2] == 'deactivate') {
                return $this->deactivate();
            }
            /*
             * Add rights to user
             */ else if ($this->segments[2] == 'rights') {
                return $this->processPostRights();
            } else {
                RestoLogUtil::httpError(404);
            }
        } else {
            /*
             * Update user
             */
            return $this->updateUser();
        }
    }

    /**
     * Process post on /administration/user/{userid}/rights
     * This post is different because it calls a delete method on rights
     * 
     * @throws Exception
     */
    private function processPostRights() {



        if (isset($this->segments[3])) {
            /*
             * This post delete rights passed with data
             */
            if ($this->segments[3] === 'delete') {
                return $this->deleteRights();
            } else if ($this->segments[3] === 'update') {
                return $this->updateRights();
            } else {
                RestoLogUtil::httpError(404);
            }
        } else {
            return $this->addRights();
        }
    }

    /**
     * updateUser - update new user in database
     * 
     * @throws Exception
     */
    private function updateUser() {
        $userParam = array_merge($_POST);
        if ($userParam) {
            try {
                $profile = $this->context->dbDriver->get(RestoDatabaseDriver::USER_PROFILE, array('userid' => $this->segments[1]));

                if (isset($userParam['groupname'])) {
                    $profile['groupname'] = $userParam['groupname'];
                }

                $this->context->dbDriver->update(RestoDatabaseDriver::USER_PROFILE, array('profile' => $profile));
                return array('status' => 'success', 'message' => 'success');
            } catch (Exception $e) {
                RestoLogUtil::httpError($e->getCode(), $e->getMessage());
            }
        } else {
            RestoLogUtil::httpError(404);
        }
    }

    private function updateRights() {



        try {
            /*
             * Get posted data
             */
            $postedData = array();
            $postedData['emailorgroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $postedData['collection'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $postedData['field'] = htmlspecialchars(filter_input(INPUT_POST, 'field'), ENT_QUOTES);
            $postedData['value'] = htmlspecialchars(filter_input(INPUT_POST, 'value'), ENT_QUOTES);

            $emailorgroup = $postedData['emailorgroup'];
            $collectionName = ($postedData['collection'] === '') ? null : $postedData['collection'];

            /*
             * Posted rights
             */
            $rights = array($postedData['field'] => $postedData['value']);

            $params = array();
            $params['emailOrGroup'] = $emailorgroup;
            $params['collectionName'] = $collectionName;

            $right = $this->context->dbDriver->get(RestoDatabaseDriver::RIGHTS, $params);
            if (!$right) {

                /*
                 * Store rights
                 */
                $params = array();
                $params['emailOrGroup'] = $emailorgroup;
                $params['collectionName'] = $collectionName;
                $params['rights'] = $rights;
                $this->context->dbDriver->store(RestoDatabaseDriver::RIGHTS, $params);

                /*
                 * Success information
                 */
                return array('status' => 'success', 'message' => 'success');
            } else {
                /*
                 * Upsate rights
                 */
                $params = array();
                $params['emailOrGroup'] = $emailorgroup;
                $params['collectionName'] = $collectionName;
                $params['rights'] = $rights;
                $this->context->dbDriver->update(RestoDatabaseDriver::RIGHTS, $params);


                /*
                 * Success information
                 */
                return array('status' => 'success', 'message' => 'success');
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Add rights 
     * 
     * @throws Exception
     */
    private function addRights() {



        try {
            /*
             * Get posted data
             */
            $postedData = array();
            $postedData['emailorgroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $postedData['collection'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $postedData['featureIdentifier'] = htmlspecialchars(filter_input(INPUT_POST, 'featureid'), ENT_QUOTES);
            $postedData['search'] = htmlspecialchars(filter_input(INPUT_POST, 'search'), ENT_QUOTES);
            $postedData['visualize'] = htmlspecialchars(filter_input(INPUT_POST, 'visualize'), ENT_QUOTES);
            $postedData['download'] = htmlspecialchars(filter_input(INPUT_POST, 'download'), ENT_QUOTES);
            $postedData['canput'] = htmlspecialchars(filter_input(INPUT_POST, 'canput'), ENT_QUOTES);
            $postedData['canpost'] = htmlspecialchars(filter_input(INPUT_POST, 'canpost'), ENT_QUOTES);
            $postedData['candelete'] = htmlspecialchars(filter_input(INPUT_POST, 'candelete'), ENT_QUOTES);
            $postedData['filters'] = filter_input(INPUT_POST, 'filters') === 'null' ? null : htmlspecialchars(filter_input(INPUT_POST, 'filters'), ENT_QUOTES);

            if (!$this->context->dbDriver->check(RestoDatabaseDriver::FEATURE, $postedData)) {
                throw new Exception('Feature does not exists', 4004);
            }


            $emailorgroup = $postedData['emailorgroup'];
            $collectionName = ($postedData['collection'] === '') ? null : $postedData['collection'];
            $featureIdentifier = ($postedData['featureIdentifier'] === '') ? null : $postedData['featureIdentifier'];

            /*
             * Posted rights
             */
            $rights = array('search' => $postedData['search'], 'visualize' => $postedData['visualize'], 'download' => $postedData['download'], 'canput' => $postedData['canput'], 'canpost' => $postedData['canpost'], 'candelete' => $postedData['candelete'], 'filters' => $postedData['filters']);

            /*
             * Store rights
             */
            $params = array();
            $params['emailOrGroup'] = $emailorgroup;
            $params['collectionName'] = $collectionName;
            $params['featureIdentifier'] = $featureIdentifier;
            $params['rights'] = $rights;

            if ($this->context->dbDriver->get(RestoDatabaseDriver::RIGHTS, $params) !== null) {
                throw new Exception('Right already exists for this feature', 4004);
            }

            $this->context->dbDriver->store(RestoDatabaseDriver::RIGHTS, $params);

            /*
             * Success information
             */
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode(), $e->getMessage());
        }
    }

    /**
     * Delete rights
     * 
     * @throws Exception
     */
    private function deleteRights() {



        try {

            $rights = array();
            $rights['emailOrGroup'] = htmlspecialchars(filter_input(INPUT_POST, 'emailorgroup'), ENT_QUOTES);
            $rights['collectionName'] = htmlspecialchars(filter_input(INPUT_POST, 'collection'), ENT_QUOTES);
            $rights['featureIdentifier'] = htmlspecialchars(filter_input(INPUT_POST, 'featureid'), ENT_QUOTES);

            $rights['collectionName'] = $rights['collectionName'] === '' ? null : $rights['collectionName'];
            $rights['featureIdentifier'] = $rights['featureIdentifier'] === '' ? null : $rights['featureIdentifier'];

            $this->context->dbDriver->remove(RestoDatabaseDriver::RIGHTS, $rights);

            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            RestoLogUtil::httpError($e->getCode());
        }
    }

    /**
     * Activate user
     * 
     * @throws Exception
     */
    private function activate() {



        try {
            $params = array();
            $params['userid'] = $this->segments[1];
            $this->context->dbDriver->execute(RestoDatabaseDriver::ACTIVATE_USER, $params);
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            throw new Exception($e->getMessage, $e->getCode);
        }
    }

    /**
     * Deactivate user
     * 
     * @throws Exception
     */
    private function deactivate() {



        try {
            $params = array();
            $params['userid'] = $this->segments[1];
            $this->context->dbDriver->execute(RestoDatabaseDriver::DEACTIVATE_USER, $params);
            return array('status' => 'success', 'message' => 'success');
        } catch (Exception $e) {
            throw new Exception($e->getMessage, $e->getCode);
        }
    }

    /**
     * Process statistics
     * 
     * @return type
     * @throws Exception
     */
    private function processStatistics() {



        switch ($this->segments[1]) {
            case 'collections':
                return $this->to($this->statisticsService());
            case 'users':
                if (!isset($this->segments[2])) {
                    return $this->to($this->statisticsUsers());
                } else if (isset($this->segments[2]) && !isset($this->segments[3])) {
                    return $this->to($this->statisticsService($this->segments[2]));
                } else {
                    throw new Exception(null, 404);
                }
                break;
            default:
                break;
        }
    }

    /**
     * Statistics over users
     * 
     * @return type
     */
    private function statisticsUsers() {



        /**
         * nb users
         * nb download
         * nb visualize
         * nb 
         */
        $statistics = array();
        $statistics['users'] = $this->countUsers();
        $statistics['download'] = $this->countService('download');
        $statistics['search'] = $this->countService('search');
        $statistics['visualize'] = $this->countService('resource');
        $statistics['insert'] = $this->countService('insert');
        $statistics['create'] = $this->countService('create');
        $statistics['update'] = $this->countService('update');
        $statistics['remove'] = $this->countService('remove');
        return $statistics;
    }

    /**
     * statisticsService - services stats on collections
     * 
     * @param int $userid
     * @return type
     */
    private function statisticsService($userid = null) {



        /*
         * Statistics for each collections
         */
        $statistics = array();
        $collections = $this->context->dbDriver->get(RestoDatabaseDriver::COLLECTIONS);
        foreach ($collections as $collection) {
            $collection_statistics = array();
            $collection_statistics['download'] = $this->countService('download', $collection['collection'], $userid);
            $collection_statistics['search'] = $this->countService('search', $collection['collection'], $userid);
            $collection_statistics['visualize'] = $this->countService('resource', $collection['collection'], $userid);
            $collection_statistics['insert'] = $this->countService('insert', $collection['collection'], $userid);
            $collection_statistics['create'] = $this->countService('create', $collection['collection'], $userid);
            $collection_statistics['update'] = $this->countService('update', $collection['collection'], $userid);
            $collection_statistics['remove'] = $this->countService('remove', $collection['collection'], $userid);
            $statistics[$collection['collection']] = $collection_statistics;
        }
        return $statistics;
    }

    /**
     * Output collection description as a JSON stream
     * 
     * @param boolean $pretty : true to return pretty print
     */
    public function toJSON($pretty = false) {



        return RestoUtil::json_format($this->data, $pretty);
    }

    /**
     * to - return method depending on return type
     * 
     * @param String $file
     * @param array $data
     * @return method
     * @throws Exception
     */
    private function to($data) {



        return $data;
    }

    /**
     * Get users profile
     * 
     * @param type $keyword
     * @param type $min
     * @param type $number
     * @return array
     * @throws Exception
     */
    public function getUsersProfiles($keyword = null, $min = 0, $number = 50) {



        try {
            $results = pg_query($this->context->dbDriver->dbh, 'SELECT userid, email, groupname, username, givenname, lastname, registrationdate, activated, connected FROM usermanagement.users ' . (isset($keyword) ? 'WHERE email LIKE \'%' . $keyword . '%\' OR username LIKE \'%' . $keyword . '%\' OR groupname LIKE \'%' . $keyword . '%\' OR givenname LIKE \'%' . $keyword . '%\' OR lastname LIKE \'%' . $keyword . '%\'' : '') . ' LIMIT ' . $number . ' OFFSET ' . $min);
            if (!$results) {
                throw new Exception();
            }
        } catch (Exception $e) {
            RestoLogUtil::httpError(500, 'Cannot get profiles for users');
        }
        $usersProfile = array();
        while ($user = pg_fetch_assoc($results)) {
            if (!$user) {
                return $usersProfile;
            }
            $user['activated'] = $user['activated'] === 't' ? true : false;
            $user['registrationdate'] = substr(str_replace(' ', 'T', $user['registrationdate']), 0, 19) . 'Z';

            $usersProfile[] = $user;
        }

        return $usersProfile;
    }

    /**
     * Count history logs per service
     * 
     * @param string $service : i.e. one of 'download', 'search', etc.
     * @param string $collectionName
     * @param integer $userid
     * @return integer
     * @throws Exception
     */
    public function countService($service, $collectionName = null, $userid = null) {



        $results = pg_query($this->context->dbDriver->dbh, 'SELECT count(gid) FROM usermanagement.history WHERE service=\'' . pg_escape_string($service) . '\'' . (isset($collectionName) ? ' AND collection=\'' . pg_escape_string($collectionName) . '\'' : '') . (isset($userid) ? ' AND userid=\'' . pg_escape_string($userid) . '\'' : ''));
        if (!$results) {
            RestoLogUtil::httpError(500, 'Database connection error');
        }
        return pg_fetch_assoc($results);
    }

    /**
     * Count history logs per service
     * 
     * @param boolean $activated
     * @param string $groupname
     * @return integer
     * @throws Exception
     */
    public function countUsers($activated = null, $groupname = null) {



        $results = pg_query($this->context->dbDriver->dbh, 'SELECT COUNT(*) FROM usermanagement.users ' . (isset($activated) ? (' WHERE activated=\'' . ($activated === true ? 't' : 'f') . '\'') : '') . (isset($groupname) ? ' AND groupname=\'' . pg_escape_string($groupname) . '\'' : ''));
        if (!$results) {
            RestoLogUtil::httpError(500, 'Database connection error');
        }
        return pg_fetch_assoc($results);
    }

    /**
     * Get user history
     * 
     * @param integer $userid
     * @param array $options
     *          
     *      array(
     *         'orderBy' => // order field (default querytime),
     *         'ascOrDesc' => // ASC or DESC (default DESC)
     *         'collectionName' => // collection name
     *         'service' => // 'search', 'download' or 'visualize' (default null),
     *         'startIndex' => // (default 0),
     *         'numberOfResults' => // (default 50),
     *         'maxDate' => // 
     *         'minDate' => // 
     *     )
     *          
     * @return array
     * @throws Exception
     */
    public function getHistory($userid = null, $options = array()) {

        $result = array();

        $orderBy = isset($options['orderBy']) ? $options['orderBy'] : 'querytime';
        $ascOrDesc = isset($options['ascOrDesc']) ? $options['ascOrDesc'] : 'DESC';
        $startIndex = isset($options['startIndex']) ? $options['startIndex'] : 0;
        $numberOfResults = isset($options['numberOfResults']) ? $options['numberOfResults'] : 50;

        $where = array();
        if (isset($userid)) {
            $where[] = 'userid=' . pg_escape_string($userid);
        }
        if (isset($options['service'])) {
            $where[] = 'service=\'' . pg_escape_string($options['service']) . '\'';
        }
        if (isset($options['method'])) {
            $where[] = 'method=\'' . pg_escape_string($options['method']) . '\'';
        }
        if (isset($options['collection'])) {
            $where[] = 'collection=\'' . pg_escape_string($options['collection']) . '\'';
        }
        if (isset($options['maxDate'])) {
            $where[] = 'querytime <=\'' . pg_escape_string($options['maxDate']) . '\'';
        }
        if (isset($options['minDate'])) {
            $where[] = 'querytime >=\'' . pg_escape_string($options['minDate']) . '\'';
        }

        $results = pg_query($this->context->dbDriver->dbh, 'SELECT gid, userid, method, service, collection, resourceid, query, querytime, url, ip FROM usermanagement.history' . (count($where) > 0 ? ' WHERE ' . join(' AND ', $where) : '') . ' ORDER BY ' . pg_escape_string($orderBy) . ' ' . pg_escape_string($ascOrDesc) . ' LIMIT ' . $numberOfResults . ' OFFSET ' . $startIndex);
        while ($row = pg_fetch_assoc($results)) {
            $result[] = $row;
        }
        return $result;
    }

}
