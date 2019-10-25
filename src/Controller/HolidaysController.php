<?php
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;

use WC\Backoffice\Utils\BusinessDateTool;

use ICal\ICal;

/**
* Holidays Controller
*
* @property \App\Model\Table\HolidayTable $Holidays
*/
class HolidaysController extends AppController
{
// RemittanceReportReader
    var $ical_sources = [];

    var $local_timezone;

    public function initialize()
    {
        parent::initialize();

        // Set local timezone object
        $this->local_timezone = new \DateTimezone('Asia/Hong_Kong');

        // Setup for iCal sources
        $this-> ical_sources['hk'] = [
            'url'=>Configure::read('WC.holiday_hk_ical_url'),
            'timezone'=>'GMT',
        ];

        // If empty, use default url
        if (empty($this-> ical_sources['hk']['url'])) {
            $this-> ical_sources['hk']['url'] = 'http://www.1823.gov.hk/common/ical/en.ics';
        }
    }
    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        //$this->log("beforeFilter", 'debug');
        //$this->log($event, 'debug');
        // Allow users to register and logout.
        // You should not add the "login" action to allow list. Doing so would
        // cause problems with normal functioning of AuthComponent.
        
        // Danger: allowing below line make all actions access without credential
        //$this->Auth->allow();
        //
        $user = $this->Auth->user();

        if ($user) {
            $this->Auth->allow();
        }
    }
    /**
    * Index method
    *
    * @return \Cake\Network\Response|null
    */
    public function index()
    {
    }

    /**
    * View method
    *
    * @param string|null $id Holiday id.
    * @return \Cake\Network\Response|null
    * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
    */
    public function view($id = null)
    {
        $holiday = $this->Holidays->get($id, [
            'contain' => []
            ]);

        $this->set('holiday', $holiday);
        $this->set('_serialize', ['holiday']);
    }

    /**
    * Add method
    *
    * @return \Cake\Network\Response|void Redirects on successful add, renders view otherwise.
    */
    public function add()
    {
        $entity = $this->Holidays->newEntity();
        if ($this->request->is('post')) {
            $data = $this->request->data;

            $success = true;

            if (!empty($data['holiday_date'])) {
                $query = $this->Holidays->findByHolidayDate($data['holiday_date']);

                if ($query->count()>0) {
                    $this->Flash->error(__('The date is already occupied, please select another date.'));
                    $success = false;
                }
            }

            if ($success) {
                $data['create_time'] = getCurrentTimeStampString();

                $entity = $this->Holidays->patchEntity($entity, $data);

                

                if ($this->Holidays->save($entity)) {
                    $this->Flash->success(__('The entity has been saved.'));
                } else {
                    // debug($entity->errors());
                    $this->Flash->error(__('The entity could not be saved. Please, try again.'));
                }
            }
        }
        return $this->redirect(['action' => 'index']);
        // $this->set(compact('entity'));
        // $this->set('_serialize', ['entity']);
    }

    /**
    * Edit method
    *
    * @param string|null $id Holiday id.
    * @return \Cake\Network\Response|void Redirects on successful edit, renders view otherwise.
    * @throws \Cake\Network\Exception\NotFoundException When record not found.
    */
    public function edit($id = null)
    {
        $entity = $this->Holidays->get($id, [
            'contain' => []
            ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $entity = $this->Holidays->patchEntity($entity, $this->request->data);
            if ($this->Holidays->save($entity)) {
                $this->Flash->success(__('The entity has been saved.'));
                return $this->redirect(['action' => 'index']);
            } else {
                $this->Flash->error(__('The entity could not be saved. Please, try again.'));
            }
        }
        $this->set(compact('entity'));
        $this->set('_serialize', ['entity']);
    }

    /**
    * Delete method
    *
    * @param string|null $id Holiday id.
    * @param string|null $id Holiday id.
    * @return \Cake\Network\Response|null Redirects to index.
    * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
    */
    public function delete($id = null, $format = 'json')
    {
        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        if (empty($id) && isset($this->request->data['id'])) {
            $id = $this->request->data['id'];
        }

        $this->request->allowMethod(['post', 'delete']);
        $holiday = $this->Holidays->get($id);

        $is_deleted = false;
        if ($this->Holidays->delete($holiday)) {
            $is_deleted = true;
        }
        if ($this->request->is('ajax')) {
            // Error on unknown type
            if (!isset($formats[$format])) {
                throw new NotFoundException(__('Unknown format.'));
            }

            $response = ['status'=>'unknown'];

            if ($is_deleted) {
                $response['status'] = 'done';
            }
            
            $this->set([
                'response' => $response,
                '_serialize' => 'response'
            ]);
            return;
        }

        if ($is_deleted) {
            $this->Flash->success(__('The holiday has been deleted.'));
        } else {
            $this->Flash->error(__('The holiday could not be deleted. Please, try again.'));
        }
        return $this->redirect(['action' => 'index']);
    }


    /*
     * Return article list in json
     */
    public function search($format = 'json')
    {
        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }

        $this->log("search", 'debug');
        $this->log($this->request->query, 'debug');
        $data = ['status'=>'failed'];

        $query = $this->Holidays->find('all', array(
            'order' => array('Holidays.holiday_date DESC')
        ));

        $total = $query->count();
        if (isset($this->request->query['page']) && isset($this->request->query['pageSize'])) {
            $query->limit($this->request->query['pageSize'])
                ->page($this->request->query['page']);
        }


        //$this->log($query->__debugInfo(), 'debug');
        $this->log($query, 'debug');

        $res = $query->toArray();
        //Add data to Array
        foreach ($res as $k => $r) {
            $res[$k]['edit_url'] = Router::url(['action' =>'edit', 'id'=>$r['id']]);
            $res[$k]['delete_url'] = Router::url(['action' =>'delete', 'id'=>$r['id']]);
        }
        $data = ['status'=>'done', 'msg'=>'Success', 'data'=>$res, 'total'=>$total];


        // Set Out Format View
        $this->viewBuilder()->className($formats[ $format ]);

        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
    }

    /**
    * Preview list of holidays by iCal url
    *
    * @param  string $region The region id. Default is 'hk'
    * @param  string $format The output format. Supported formats are 'xml', 'json'(Default).
    * @return void Renders view .
    */
    public function previewCal($region = 'hk', $format = 'json')
    {
        $format = strtolower($format);

        // Format to view mapping
        $formats = [
            'xml' => 'Xml',
            'json' => 'Json',
        ];

        // Error on unknown type
        if (!isset($formats[$format])) {
            throw new NotFoundException(__('Unknown format.'));
        }

        if (!isset($this-> ical_sources[ $region ])) {
            throw new NotFoundException(__('Unknown region.'));
        }

        $ical_url = $this-> ical_sources[ $region ]['url'];
        $ical_timezone = $this-> ical_sources[ $region ]['timezone'];

        // getting the result
        $events = $this->parseCalUrlToEvents($ical_url, $ical_timezone);

        // Set Out Format View
        $this->viewBuilder()->className($formats[ $format ]);

        // Send to browser for previewing the result
        $this->set(['result'=> array('url'=> $ical_url , 'events'=>$events)]);
        $this->set('_serialize', ['result']);
    }

    /**
     * Import list of holidays by iCal url
     *
     * @param  string $region The region id. Default is 'hk'
     */
    public function importCal($region = 'hk')
    {
        if ($this->request->is(['get'])) {
            if (!isset($this-> ical_sources[ $region ])) {
                throw new NotFoundException(__('Unknown region.'));
            }

            $ical_url = $this-> ical_sources[ $region ]['url'];
            $ical_timezone = $this->ical_sources[$region]['timezone'];

            // getting the result
            $events = $this->parseCalUrlToEvents($ical_url, $ical_timezone);


            $counter = 0;

            // List all events if any returned
            if (is_array($events)) {
                foreach ($events as $event) {
                    // If Holiday Date is duplicated
                    $query = $this->Holidays->findByHolidayDate($event['holiday_date']);
                    if ($query->count()>0) {
                        continue;
                    }

                    // If ical_id is duplicated, assume we change the same record
                    $query = $this->Holidays->findByIcalId($event['ical_id']);

                    $entity  = null;

                    $is_new = false;

                    if ($query->count() > 0) {
                        $entity = $query->first();
                    }
                    
                    //debug($entity);

                    // Prepare new entries from schema
                    if (empty($entity)) {
                        $entity = $this->Holidays->newEntity();
                        $is_new = true;
                        $event['create_time'] = getCurrentTimeStampString();
                    }

                    $event['source'] = 'ical_'.$region;

                    // Update entry content
                    $entity = $this->Holidays->patchEntity($entity, $event);

                    // Save the new content
                    if ($this->Holidays->save($entity)) {
                        if ($is_new) {
                            $counter++;
                        }
                    } else {
                        debug($entity->errors());
                    }
                }
            }

            $this->Flash->success(__('Imported '.$counter.' new events from iCal'));
            return $this->redirect(['action' => 'index']);
        }
    }

    /**
     * Convert content of iCal url into list of events.
     *
     * @param  string Url for parsing
     * @return array  Return 1-D array will be extracted. Each element is an object contain "name", "date"
     */
    protected function parseCalUrlToEvents($url, $fromTimezone = 'GMT')
    {
        // Create instance
        $ical = new ICal();
        $ical->initUrl($url);
        $events = $ical->events();

        //return $events;

        // Prepare the output array
        $output = [];

        // Create timezone reference
        $timezone = new \DateTimezone($fromTimezone);
        $hk_timezone = $this->local_timezone;

        // List all events if any returned
        if (is_array($events)) {
            foreach ($events as $event) {
                // Extracting data from the event object
                $start_dt = new \Datetime($event->dtstart_tz, $timezone);
                $end_dt = new \Datetime($event->dtend_tz, $timezone);
                $event_name = $event->summary;

                // Generate the list of holiday between 2 date
                $daterange = new \DatePeriod(
                    $start_dt,
                    new \DateInterval('P1D'),
                    $end_dt
                );

                foreach ($daterange as $date) {
                    // $holiday = $holidays->newEntity();

                    // $holiday = $holidays->patchEntity($holiday, [
                    //     'name'=>$event_name,
                    //     'holiday_date'=>$date->format('Y-m-d', $hk_timezone)
                    //]);
                    $date->setTimezone($hk_timezone);

                    $item = [];
                    $item['name'] = $event_name;
                    $item['ical_id'] = $event->uid.'-'.$date->format('Ymd');
                    $item['holiday_date'] = $date->format('Y-m-d');
                    $output[] =  $item;
                }
            }
        }

        return $output;
    }

    public function testRange($fromDateStr = 'now')
    {
        $numDays = 90;



        if ($this->request->query('fromDate') !== null) {
            $fromDateStr = $this->request->query('fromDate');
        }


        $fromDate = new \Datetime($fromDateStr, $this->local_timezone);

        $holidayRangeStart = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeStart->add(new \DateInterval('P3M'));

        $holidayRangeEnd = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeEnd->sub(new \DateInterval('P3M'));

        $query = $this->Holidays->find('all', array(
            'order' => array('Holidays.holiday_date DESC')
        ))
        ->limit(20)
        ->where(function ($exp) use ($holidayRangeStart, $holidayRangeEnd) {
            return $exp
                ->lt('holiday_date', $holidayRangeStart->format('Y-m-d'))
                ->gt('holiday_date', $holidayRangeEnd->format('Y-m-d'));
        });

        $holidays = [];

        $res = $query->toArray();
        foreach ($res as $k => $r) {
            $holidays[] = new \Datetime($r['holiday_date'], $this->local_timezone);
        }


        $finder = new BusinessDateTool();
        $finder->setHolidays($holidays);

        echo '<table>';
        echo '<tr>';
        echo '<th>Test Date</th>';
        echo '<th>No .of day</th>';
        echo '<th>Range Start</th>';
        echo '<th>Range End</th>';
        echo '</tr>';
        for ($i = 0; $i <= $numDays; $i++) {
            $testDate = clone $fromDate;
            $testDate->add(new \DateInterval('P'.$i.'D'));

            $result = $finder->findLastProcessDate($testDate);

            print '<tr>';
            print "<td>".$testDate->format('Y-m-d').'</td>';
            print '<td>'.$i.'</td>';

            if (!empty($result['range_start'])) {
                print '<td>'.$result['range_start']->format('Y-m-d').'</td>';
                print '<td>'.$result['range_end']->format('Y-m-d').'</td>';
            } else {
                print '<td>Non-process day.</td><td></td>';
            }

            print '</tr>';
        }

        echo '</table>';
        exit;
    }

    public function lastBusinessDateFromDate($fromDateStr = 'now', $t = 2)
    {
        $query = $this->Holidays->find('all', array());

        if ($this->request->query('fromDate') !== null) {
            $fromDateStr = $this->request->query('fromDate');
        }

        $t = 2;
        if ($this->request->query('t') !== null) {
            $t = intval($this->request->query('t'));
        }

        $fromDate = new \Datetime($fromDateStr, $this->local_timezone);

        $holidayRangeStart = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeStart->add(new \DateInterval('P3M'));

        $holidayRangeEnd = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeEnd->sub(new \DateInterval('P3M'));

        $query = $this->Holidays->find('all', array(
            'order' => array('Holidays.holiday_date DESC')
        ))
        ->limit(20)
        ->where(function ($exp) use ($holidayRangeStart, $holidayRangeEnd) {
            return $exp
                ->lt('holiday_date', $holidayRangeStart->format('Y-m-d'))
                ->gt('holiday_date', $holidayRangeEnd->format('Y-m-d'));
        });

        $holidays = [];

        $res = $query->toArray();
        foreach ($res as $k => $r) {
            $holidays[] = new \Datetime($r['holiday_date'], $this->local_timezone);
        }

        $finder = new BusinessDateTool();
        $finder->setHolidays($holidays);


        $data = $finder->findLastProcessDate($fromDate, $t);

        // Set Out Format View
        $this->viewBuilder()->className('Json');

        $this->set([
            'response' => $data,
            '_serialize' => 'response'
        ]);
    }

    public function testBusinessDateFromDate($fromDateStr = 'now')
    {
        $query = $this->Holidays->find('all', array());


        if ($this->request->query('fromDate')!==null) {
            $fromDateStr = $this->request->query('fromDate');
        }

        $fromDateStr = '2017-05-24';

        $fromDate = new \Datetime($fromDateStr, $this->local_timezone);
        
        $holidayRangeStart = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeStart->sub(new \DateInterval('P3M'));

        $holidayRangeEnd = new \Datetime($fromDateStr, $this->local_timezone);
        $holidayRangeEnd->add(new \DateInterval('P3M'));

        $query = $this->Holidays->find('all', array(
            'order' => array('Holidays.holiday_date DESC')
        ))
        ->limit(20)
        ->where(function ($exp) use ($holidayRangeStart, $holidayRangeEnd) {
            return $exp
                ->gt('holiday_date', $holidayRangeStart->format('Y-m-d'))
                ->lt('holiday_date', $holidayRangeEnd->format('Y-m-d'));
        });

        $holidays = [];

        $res = $query->toArray();
        foreach ($res as $k => $r) {
            $holidays[] = new \Datetime($r['holiday_date'], $this->local_timezone);
        }

        $finder = new BusinessDateTool();
        $finder->setHolidays($holidays);

        for ($i = 0; $i <= 10; $i ++) {
            $targetDate = new \DateTime($fromDate->format(\DateTime::ATOM));
            $targetDate->add(new \DateInterval('P'.$i.'D'));
            $result = $finder->findLastProcessDate($targetDate);
            $data[ $targetDate->format('Y-m-d') ] = $result;
        }


        $verify = [];

        $verify['2017-05-24'] = [
            'range_start'=>$data ['2017-05-24']['range_start']->format('Y-m-d') == '2017-05-22',
            'range_end'=>$data ['2017-05-24']['range_end']->format('Y-m-d') == '2017-05-22',
        ];
        $verify['2017-05-25'] = [
            'range_start'=>$data ['2017-05-25']['range_start']->format('Y-m-d') == '2017-05-23',
            'range_end'=>$data ['2017-05-25']['range_end']->format('Y-m-d') == '2017-05-23',
        ];
        $verify['2017-05-26'] = [
            'range_start'=>$data ['2017-05-26']['range_start']->format('Y-m-d') == '2017-05-24',
            'range_end'=>$data ['2017-05-26']['range_end']->format('Y-m-d') == '2017-05-24',
        ];
        $verify['2017-05-27'] = [
            'range_start'=>$data ['2017-05-27']['range_start'] == null,
            'range_end'=>$data ['2017-05-27']['range_end'] == null,
        ];
        $verify['2017-05-28'] = [
            'range_start'=>$data ['2017-05-28']['range_start'] == null,
            'range_end'=>$data ['2017-05-28']['range_end'] == null,
        ];
        $verify['2017-05-29'] = [
            'range_start'=>$data ['2017-05-29']['range_start']->format('Y-m-d') == '2017-05-25',
            'range_end'=>$data ['2017-05-29']['range_end']->format('Y-m-d') == '2017-05-25',
        ];
        $verify['2017-05-30'] = [
            'range_start'=>$data ['2017-05-30']['range_start'] == null,
            'range_end'=>$data ['2017-05-30']['range_end'] == null,
        ];
        $verify['2017-05-31'] = [
            'range_start'=>$data ['2017-05-31']['range_start']->format('Y-m-d') == '2017-05-26',
            'range_end'=>$data ['2017-05-31']['range_end']->format('Y-m-d') == '2017-05-28',
        ];
        $verify['2017-06-01'] = [
            'range_start'=>$data ['2017-06-01']['range_start']->format('Y-m-d') == '2017-05-29',
            'range_end'=>$data ['2017-06-01']['range_end']->format('Y-m-d') == '2017-05-30',
        ];
        $verify['2017-06-02'] = [
            'range_start'=>$data ['2017-06-02']['range_start']->format('Y-m-d') == '2017-05-31',
            'range_end'=>$data ['2017-06-02']['range_end']->format('Y-m-d') == '2017-05-31',
        ];


        // Set Out Format View
        $this->viewBuilder()->className('Json');

        $this->set([
            'response' => compact('verify', 'data', 'holidays'),
            '_serialize' => 'response'
        ]);
    }
}
