<?php
App::uses('TimeTrackerAppController', 'TimeTracker.Controller');
/**
 * TimeTrackerActivities Controller
 *
 * @property TimeTrackerActivity $TimeTrackerActivity
 * @property PaginatorComponent $Paginator
 * @property SessionComponent $Session
 */
class TimeTrackerActivitiesController extends TimeTrackerAppController {

/**
 * Components
 *
 * @var array
 */
    public $components = array('Paginator', 'Session');

    public $paginate = array(
        'limit' => 25,
        'contain' => array('User')
    );

/**
 * index method
 *
 * @return void
 */
    public function index() {
        $this->TimeTrackerActivity->recursive = 0;
        $this->TimeTrackerActivity->contain = array('User');
        $this->set('timeTrackerActivities', $this->Paginator->paginate());
    }

/**
 * view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
    public function view($id = null) {
        if (!$this->TimeTrackerActivity->exists($id)) {
            throw new NotFoundException(__('Invalid time tracker activity'));
        }

        // Recherche des TimeTrackerActivity et Users
        $conditions = array(
            'TimeTrackerActivity.id' => $id

        );
        $order  = array('TimeTrackerActivity.date ASC');
        $fields = array(
            'TimeTrackerActivity.id',
            'TimeTrackerActivity.date',
            'TimeTrackerActivity.duration',
            'TimeTrackerActivity.comment',
            'TimeTrackerActivity.created',
            'TimeTrackerActivity.modified',
            'TimeTrackerCategory.id',
            'TimeTrackerCategory.name',
            Configure::read('user.model') . '.id',
            Configure::read('user.model') . '.firstname',
            Configure::read('user.model') . '.lastname',
            'TimeTrackerCustomer.id',
            'TimeTrackerCustomer.name',

        );
        $TimeTrackerActivity    = ClassRegistry::init('TimeTracker.TimeTrackerActivity');
        $timeTrackerActivity = $this->TimeTrackerActivity->find('first', array('conditions' => $conditions, 'order' => $order, 'fields' => $fields));


        $this->set(compact('timeTrackerActivity'));
    }

/**
 * add method
 *
 * @return void
 */
    public function add($date = null) {

        // Recovery activities that date
        $activitiesUserByDate = array();
        $dateFilter = '';
        if(!empty($date)){
            $conditions = array(
                'TimeTrackerActivity.date'    => $date,
                'TimeTrackerActivity.user_id' => $this->Auth->user('id'),
            );
            $contain = array('TimeTrackerCategory');
            $activitiesUserByDate = $this->TimeTrackerActivity->find('all', array('conditions' => $conditions, 'contain' => $contain));

            $dateFilter = $date;



        }
        if ($this->request->is('post')) {
            $dataToSave = $this->request->data;

            // Recovery time remaining for this date
            $durationAll = $this->TimeTrackerActivity->durationToDayByUser($date, $this->Auth->user('id'));
            $timeLeft    = TimeUtil::subtractionTime(Configure::read('hoursInWorkDay'), $durationAll);

            // Prepare array
            //$dataToSave['TimeTrackerActivity']['date']     = date('Y/m/d', strtotime(str_replace('/', '-', $dataToSave['TimeTrackerActivity']['date'])));
            $dataToSave['TimeTrackerActivity']['user_id']  = $this->Auth->user('id');
            pr($timeLeft);
            pr($dataToSave['TimeTrackerActivity']['duration']);
            if($dataToSave['TimeTrackerActivity']['duration'] > $timeLeft) {
                $this->Session->setFlash(__('The seizure duration is greater than the time remaining on that date. Please, try again.'));
                return $this->redirect($this->referer());
            }

            $this->TimeTrackerActivity->create();
            if ($this->TimeTrackerActivity->save($dataToSave)) {
                $this->Session->setFlash(__('The time tracker activity has been saved.'));
                return $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The time tracker activity could not be saved. Please, try again.'));
            }
        } else {
            $timeTrackerCustomers = $this->TimeTrackerActivity->TimeTrackerCustomer->find('list');
            $timeTrackerCategories = $this->TimeTrackerActivity->TimeTrackerCategory->generateTreeList(null, null, null, '　');
            $this->set(compact('timeTrackerCustomers', 'timeTrackerCategories'));
        }


        $this->set(compact('timeTrackerCustomers', 'timeTrackerCategories', 'activitiesUserByDate', 'dateFilter'));
    }

/**
 * edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
    public function edit($id = null) {
        if (!$this->TimeTrackerActivity->exists($id)) {
            throw new NotFoundException(__('Invalid time tracker activity'));
        }

        $timeTrackerActivity = $this->TimeTrackerActivity->findById($id, array('fields' => 'user_id'));
        if($this->Auth->user('id') != $timeTrackerActivity['TimeTrackerActivity']['user_id']){
            $this->Session->setFlash(__('You can not change what he does not belong to you.'));
            return $this->redirect($this->referer());
        }

        if ($this->request->is(array('post', 'put'))) {

            $dataToSave = $this->request->data;
            $dataToSave['TimeTrackerActivity']['user_id'] = $this->Auth->user('id');

            // Recovery time remaining for this date
            $durationAll = $this->TimeTrackerActivity->durationToDayByUser($date, $this->Auth->user('id'));
            $timeLeft    = TimeUtil::subtractionTime(Configure::read('hoursInWorkDay'), $durationAll);

            if($dataToSave['TimeTrackerActivity']['duration'] > $timeLeft) {
                $this->Session->setFlash(__('The seizure duration is greater than the time remaining on that date. Please, try again.'));
                return $this->redirect($this->referer());
            }

            $this->TimeTrackerActivity->create();
            if ($this->TimeTrackerActivity->save($dataToSave)) {
                $this->Session->setFlash(__('The time tracker activity has been saved.'));
                return $this->redirect(array('action' => 'index'));
            } else {
                $this->Session->setFlash(__('The time tracker activity could not be saved. Please, try again.'));
            }
        } else {
            $options = array('conditions' => array('TimeTrackerActivity.' . $this->TimeTrackerActivity->primaryKey => $id));
            $this->request->data = $this->TimeTrackerActivity->find('first', $options);
            if ($this->request->data['TimeTrackerActivity']['date']) {
                $this->request->data['TimeTrackerActivity']['date'] = $this->request->data['TimeTrackerActivity']['date_humanized'];
            }
        }
        $users = $this->TimeTrackerActivity->User->find('list');
        $timeTrackerCustomers = $this->TimeTrackerActivity->TimeTrackerCustomer->find('list');
        $timeTrackerCategories = $this->TimeTrackerActivity->TimeTrackerCategory->generateTreeList(null, null, null, '　');
        $this->set(compact('timeTrackerCustomers', 'timeTrackerCategories'));
    }

/**
 * delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
    public function delete($id = null) {
        $this->TimeTrackerActivity->id = $id;
        if (!$this->TimeTrackerActivity->exists()) {
            throw new NotFoundException(__('Invalid time tracker activity'));
        }

        $timeTrackerActivity = $this->TimeTrackerActivity->findById($id, array('fields' => 'user_id'));
        if($this->Auth->user('id') != $timeTrackerActivity['TimeTrackerActivity']['user_id']){
            $this->Session->setFlash(__('You can not delete what is not yours.'));
            return $this->redirect($this->referer());
        }

        if ($this->TimeTrackerActivity->delete()) {
            $this->Session->setFlash(__('The time tracker activity has been deleted.'));
        } else {
            $this->Session->setFlash(__('The time tracker activity could not be deleted. Please, try again.'));
        }
        return $this->redirect(array('action' => 'index'));
    }
}
