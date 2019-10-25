<?php 

class BusinessDateTool
{
	/**
	 * The list of dates that is holiday
	 * 
	 * @var array<string>
	 */
	var $holidays = [];

	/**
	 * Setup local timezone
	 * 
	 * @var DateTimezone
	 */
	var $local_timezone;


	public function __construct($timezone = 'Asia/Hong_Kong'){
		//parent::__construct();

		$this->local_timezone = new \DateTimezone($timezone); 
	}

	/**
	 * Set the list of holidays
	 * @param array $items List of items of all holiday date. Element could be 'Y-m-d' formatted date string, or DateTime object (Timezone must be same as pre-configured.)
	 */
	public function setHolidays($items){
		$this->holidays = [];


        foreach ($items as $k=>$r) {
        	if(is_string($r))
 		       	$date = new \DateTime($r, $this->local_timezone);
			else
				$date = $r;
			$this->holidays[] = $date->format('Y-m-d');
        }

        return $this;
	}

	/**
	 * Find the nearest process date (range) by a date.
	 * 
	 * @param  string|datetime  $request_date The requested date for scanning nearest process date
	 * @param  integer          $t            The T-x days
	 * @param  integer          $max_tried    No. of times for trying to scan the dates
	 * @return array                          The final result after scanning with holidays
	 */
	public function findLastProcessDate($request_date = 'now', $t = 2 , $max_tried = 30)
	{

		$request_date = is_object($request_date) ? $request_date : new \Datetime($request_dateStr, $this->local_timezone);


		// Check if the date allowed for process
		$day_of_week = $request_date->format('N');
		$is_sunday = $day_of_week == '7';
		$is_saturday = $day_of_week == '6';
		$is_holiday = is_array($this->holidays) && !empty($this->holidays) ? in_array($request_date->format('Y-m-d'), $this->holidays): false;
		$is_biz_day = !$is_sunday && !$is_saturday && !$is_holiday;

		$request_date_info = compact('date_string','date','name_day_of_week','day_of_week','is_sunday','is_saturday','is_holiday');
		$scans = [];

		$target_date = null;
		$process_date = null;

		$num_tried = 0;

		$num_includes = 0;
		$num_non_bizday = 0;

		$range_start = null;
		$range_end = null;

		$allowed = false;

		// If the requested date is allowed
		if($is_biz_day){

			$allowed = true;

			$process_dates = [];

			// Clone object from request date - 1 day
			$target_date = new \DateTime($request_date->format(\DateTime::ATOM));
			$target_date->sub(new \DateInterval('P1D'));
			$target_date->setTime(0,0,0);


			$last_is_biz_day = false;

			// Before we found the correct process date, loop here
			while($process_date == null){

				$date = $target_date;
				$date->setTime(0,0,0);
				$date_string = $date->format(\DateTime::ATOM);


				// Check with Date Formate and using 'N' character to represent the name of the day in a week
				$day_of_week = $date->format('N');
				$name_day_of_week = $date->format('l');

				$is_sunday = $day_of_week == '7';
				$is_saturday = $day_of_week == '6';
				$is_holiday = is_array($this->holidays) && !empty($this->holidays) ? in_array($date->format('Y-m-d'), $this->holidays): false;
				

				$is_biz_day = !$is_sunday && !$is_saturday && !$is_holiday;

				// For debug purpose only
				$scans[] = compact('date_string','date','name_day_of_week','day_of_week','is_sunday','is_saturday','is_holiday','num_non_bizday','is_biz_day');

				// If the date is not biz day
				if($is_biz_day){

					$num_non_bizday ++;

					// If the number of non_biz day is matched the conditions $t
					// We stop here and provide the range of necessary process date
					if($num_non_bizday >= $t){
						$process_date = clone $date;
					// Reset the time into 0 zero base
						$process_date->setTime(0,0,0);
						$process_dates [] = $process_date;
						break;
					}

					// If the date is "biz date", reset the process date list
					$process_dates = [];
				}else{

					// If the date is "non-biz date", add it into process date list
					$new_date = clone $date;
					// Reset the time into 0 zero base
					$new_date->setTime(0,0,0);

					$process_dates [] = clone $date;
				}

				$last_is_biz_day = $is_biz_day;


				// If the loop counter reach the limit, stop here
				$num_tried ++;
				if($num_tried > $max_tried){
					break;
				}

				// Prepare new date object for next scan
				$date -> sub(new \DateInterval('P1D'));
				$target_date = new \DateTime($date->format(\DateTime::ATOM));
			}

			// If found any date, provide the correct range
			if(count($process_dates) > 0){
				$range_start = clone $process_dates[ count($process_dates) - 1];
				$range_end = clone $process_dates[0];
				$range_end->setTime(0,0,0);
				$range_end->add(new \DateInterval('P1D'));
				$range_end->sub(new \DateInterval('PT1S'));
			}
		}



		return compact('allowed','request_date','request_date_info','process_dates','range_start','range_end');
	}

}