<?php
 public function dashboard()
    {
        $auth_user_id = $this->Auth->user('id');

        $last_record = Time::now();
        $first_record = user()->created;

        $year_month = [];

        $last_month = Time::now()->year($last_record->year)->month($last_record->month)->startOfMonth();
        $first_month = Time::now()->year($first_record->year)->month($first_record->month)->startOfMonth();

        while ($first_month <= $last_month) {
            $year_month[$last_month->format('Y-m')] = $last_month->i18nFormat('LLLL Y');

            $last_month->modify('-1 month');
        }

        $this->set('year_month', $year_month);

        $to_month = Time::now()->format('Y-m');
        if (
            $this->getRequest()->getQuery('month') &&
            array_key_exists($this->getRequest()->getQuery('month'), $year_month)
        ) {
            $to_month = explode('-', $this->getRequest()->getQuery('month'));
            $year = (int) $to_month[0];
            $month = (int) $to_month[1];
        } else {
            $time = new Time($to_month);
            $current_time = $time->startOfMonth();

            $year = (int) $current_time->format('Y');
            $month = (int) $current_time->format('m');
        }

        $time_zone = get_option('timezone', 'UTC');
        $date1 = Time::createFromDate($year, $month, 01, $time_zone)
            ->startOfMonth()
            ->i18nFormat('yyyy-MM-dd HH:mm:ss', 'UTC', 'en');
        $date2 = Time::createFromDate($year, $month, 01, $time_zone)
            ->endOfMonth()
            ->i18nFormat('yyyy-MM-dd HH:mm:ss', 'UTC', 'en');

        $connection = ConnectionManager::get('default');

        $time_zone_offset = Time::now($time_zone)->format('P');


        $CurrentMonthDays = Cache::read('currentMonthDays_' . $auth_user_id . '_' . $date1 . '_' . $date2, '15min');
        if ($CurrentMonthDays === false) {
            // Updated SQL query to include join with links table
            $sql = "SELECT
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          COUNT(Statistics.id)
                      END AS count,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          SUM(Statistics.publisher_earn)
                      END AS publisher_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.user_id = {$auth_user_id}
                        AND Links.plan_id = 1
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_publisher = $stmt->fetchAll('assoc');
        
            // Updated SQL query to include join with links table for referral earnings
            $sql = "SELECT
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          SUM(Statistics.referral_earn)
                      END AS referral_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.referral_id = {$auth_user_id}
                        AND Links.plan_id = 1
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_referral = $stmt->fetchAll('assoc');
        
            $CurrentMonthDays = [];
        
            $targetTime = Time::createFromDate($year, $month, 01)->startOfMonth();
        
            for ($i = 1; $i <= $targetTime->format('t'); $i++) {
                $CurrentMonthDays[$year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" .
                    str_pad($i, 2, '0', STR_PAD_LEFT)] = [
                    'view' => 0,
                    'publisher_earnings' => 0,
                    'referral_earnings' => 0,
                ];
            }
        
            foreach ($views_publisher as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDays[$day]['view'] = $view['count'];
                $CurrentMonthDays[$day]['publisher_earnings'] = $view['publisher_earnings'];
            }
            unset($view);
            foreach ($views_referral as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDays[$day]['referral_earnings'] = $view['referral_earnings'];
            }
            unset($view);
        
            if ((bool) get_option('cache_member_statistics', 1)) {
                Cache::write(
                    'currentMonthDays_' . $auth_user_id . '_' . $date1 . '_' . $date2,
                    $CurrentMonthDays,
                    '15min'
                );
            }
        }
        $this->set('CurrentMonthDays', $CurrentMonthDays);
        
        $this->set('total_views', array_sum(array_column_polyfill($CurrentMonthDays, 'view')));
        $this->set('total_earnings', array_sum(array_column_polyfill($CurrentMonthDays, 'publisher_earnings')));
        $this->set('referral_earnings', array_sum(array_column_polyfill($CurrentMonthDays, 'referral_earnings')));
        

        // level 2 pro 

        $CurrentMonthDaysPlan2 = Cache::read('currentMonthDaysPlan2_' . $auth_user_id . '_' . $date1 . '_' . $date2, '15min');
        if ($CurrentMonthDaysPlan2 === false) {
            // SQL query for publisher earnings
            $sql = "SELECT
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          COUNT(Statistics.id)
                      END AS count,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          SUM(Statistics.publisher_earn)
                      END AS publisher_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.user_id = {$auth_user_id}
                        AND Links.plan_id = 2
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_publisher_plan2 = $stmt->fetchAll('assoc');
        
            // SQL query for referral earnings
            $sql = "SELECT
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          SUM(Statistics.referral_earn)
                      END AS referral_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.referral_id = {$auth_user_id}
                        AND Links.plan_id = 2
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_referral_plan2 = $stmt->fetchAll('assoc');
        
            $CurrentMonthDaysPlan2 = [];
        
            $targetTime = Time::createFromDate($year, $month, 01)->startOfMonth();
        
            for ($i = 1; $i <= $targetTime->format('t'); $i++) {
                $CurrentMonthDaysPlan2[$year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" .
                    str_pad($i, 2, '0', STR_PAD_LEFT)] = [
                    'view' => 0,
                    'publisher_earnings' => 0,
                    'referral_earnings' => 0,
                ];
            }
        
            foreach ($views_publisher_plan2 as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDaysPlan2[$day]['view'] = $view['count'];
                $CurrentMonthDaysPlan2[$day]['publisher_earnings'] = $view['publisher_earnings'];
            }
            unset($view);
        
            foreach ($views_referral_plan2 as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDaysPlan2[$day]['referral_earnings'] = $view['referral_earnings'];
            }
            unset($view);
        
            if ((bool) get_option('cache_member_statistics', 1)) {
                Cache::write(
                    'currentMonthDaysPlan2_' . $auth_user_id . '_' . $date1 . '_' . $date2,
                    $CurrentMonthDaysPlan2,
                    '15min'
                );
            }
        }
        
        $this->set('CurrentMonthDaysPlan2', $CurrentMonthDaysPlan2);
        $this->set('total_views_plan2', array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'view')));
        $this->set('total_earnings_plan2', array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'publisher_earnings')));
        $this->set('referral_earnings_plan2', array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'referral_earnings')));
        





// level 3 pro 
        $CurrentMonthDaysPlan3 = Cache::read('CurrentMonthDaysPlan3_' . $auth_user_id . '_' . $date1 . '_' . $date2, '15min');
        if ($CurrentMonthDaysPlan3 === false) {
            // SQL query for publisher earnings
            $sql = "SELECT
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          COUNT(Statistics.id)
                      END AS count,
                      CASE
                        WHEN Statistics.publisher_earn > 0
                        THEN
                          SUM(Statistics.publisher_earn)
                      END AS publisher_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.user_id = {$auth_user_id}
                        AND Links.plan_id = 3
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_publisher_plan3 = $stmt->fetchAll('assoc');
        
            // SQL query for referral earnings
            $sql = "SELECT
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
                      END  AS day,
                      CASE
                        WHEN Statistics.referral_earn > 0
                        THEN
                          SUM(Statistics.referral_earn)
                      END AS referral_earnings
                    FROM
                      statistics Statistics
                      JOIN links Links ON Statistics.link_id = Links.id
                    WHERE
                      (
                        Statistics.created BETWEEN :date1 AND :date2
                        AND Statistics.referral_id = {$auth_user_id}
                        AND Links.plan_id = 3
                      )
                    GROUP BY
                      day";
        
            $stmt = $connection->prepare($sql);
            $stmt->bindValue('date1', $date1, 'datetime');
            $stmt->bindValue('date2', $date2, 'datetime');
            $stmt->execute();
            $views_referral_plan2 = $stmt->fetchAll('assoc');
        
            $CurrentMonthDaysPlan3 = [];
        
            $targetTime = Time::createFromDate($year, $month, 01)->startOfMonth();
        
            for ($i = 1; $i <= $targetTime->format('t'); $i++) {
                $CurrentMonthDaysPlan3[$year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" .
                    str_pad($i, 2, '0', STR_PAD_LEFT)] = [
                    'view' => 0,
                    'publisher_earnings' => 0,
                    'referral_earnings' => 0,
                ];
            }
        
            foreach ($views_publisher_plan3 as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDaysPlan3[$day]['view'] = $view['count'];
                $CurrentMonthDaysPlan3[$day]['publisher_earnings'] = $view['publisher_earnings'];
            }
            unset($view);
        
            foreach ($views_referral_plan3 as $view) {
                if (!$view['day']) {
                    continue;
                }
        
                $day = $view['day'];
                $CurrentMonthDaysPlan3[$day]['referral_earnings'] = $view['referral_earnings'];
            }
            unset($view);
        
            if ((bool) get_option('cache_member_statistics', 1)) {
                Cache::write(
                    'CurrentMonthDaysPlan3_' . $auth_user_id . '_' . $date1 . '_' . $date2,
                    $CurrentMonthDaysPlan3,
                    '15min'
                );
            }
        }
        
        $this->set('CurrentMonthDaysPlan3', $CurrentMonthDaysPlan3);
        $this->set('total_views_plan3', array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'view')));
        $this->set('total_earnings_plan3', array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'publisher_earnings')));
        $this->set('referral_earnings_plan3', array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'referral_earnings')));
        



// Combine total views
$total_views = array_sum(array_column_polyfill($CurrentMonthDays, 'view'));
       
$total_views_plan2 = array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'view'));
$total_views_plan3 = array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'view'));
$total_views_combined = $total_views_plan2 + $total_views_plan3 + $total_views;

// Combine total earnings
$total_earnings = array_sum(array_column_polyfill($CurrentMonthDays, 'publisher_earnings'));

$total_earnings_plan2 = array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'publisher_earnings'));
$total_earnings_plan3 = array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'publisher_earnings'));
$total_earnings_combined = $total_earnings_plan2 + $total_earnings_plan3 + $total_earnings;

// Combine referral earnings
$referral_earnings = array_sum(array_column_polyfill($CurrentMonthDays, 'referral_earnings'));
$referral_earnings_plan2 = array_sum(array_column_polyfill($CurrentMonthDaysPlan2, 'referral_earnings'));
$referral_earnings_plan3 = array_sum(array_column_polyfill($CurrentMonthDaysPlan3, 'referral_earnings'));
$referral_earnings_combined = $referral_earnings_plan2 + $referral_earnings_plan3 + $referral_earnings;

// Set the combined totals to be used in the view
$this->set('total_views_combined', $total_views_combined);
$this->set('total_earnings_combined', $total_earnings_combined);
$this->set('referral_earnings_combined', $referral_earnings_combined);





        // $CurrentMonthDays = Cache::read('currentMonthDays_' . $auth_user_id . '_' . $date1 . '_' . $date2, '15min');
        // if ($CurrentMonthDays === false) {
        //     $sql = "SELECT
        //           CASE
        //             WHEN Statistics.publisher_earn > 0
        //             THEN
        //               DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
        //           END  AS `day`,
        //           CASE
        //             WHEN Statistics.publisher_earn > 0
        //             THEN
        //               COUNT(Statistics.id)
        //           END AS `count`,
        //           CASE
        //             WHEN Statistics.publisher_earn > 0
        //             THEN
        //               SUM(Statistics.publisher_earn)
        //           END AS `publisher_earnings`
        //         FROM
        //           statistics Statistics
        //         WHERE
        //           (
        //             Statistics.created BETWEEN :date1 AND :date2
        //             AND Statistics.user_id = {$auth_user_id}
        //           )
        //         GROUP BY
        //           day";

        //     $stmt = $connection->prepare($sql);
        //     $stmt->bindValue('date1', $date1, 'datetime');
        //     $stmt->bindValue('date2', $date2, 'datetime');
        //     $stmt->execute();
        //     $views_publisher = $stmt->fetchAll('assoc');

        //     $sql = "SELECT
        //           CASE
        //             WHEN Statistics.referral_earn > 0
        //             THEN
        //               DATE_FORMAT(CONVERT_TZ(Statistics.created,'+00:00','" . $time_zone_offset . "'), '%Y-%m-%d')
        //           END  AS `day`,
        //           CASE
        //             WHEN Statistics.referral_earn > 0
        //             THEN
        //               SUM(Statistics.referral_earn)
        //           END AS `referral_earnings`
        //         FROM
        //           statistics Statistics
        //         WHERE
        //           (
        //             Statistics.created BETWEEN :date1 AND :date2
        //             AND Statistics.referral_id = {$auth_user_id}
        //           )
        //         GROUP BY
        //           day";

        //     $stmt = $connection->prepare($sql);
        //     $stmt->bindValue('date1', $date1, 'datetime');
        //     $stmt->bindValue('date2', $date2, 'datetime');
        //     $stmt->execute();
        //     $views_referral = $stmt->fetchAll('assoc');

        //     $CurrentMonthDays = [];

        //     $targetTime = Time::createFromDate($year, $month, 01)->startOfMonth();

        //     for ($i = 1; $i <= $targetTime->format('t'); $i++) {
        //         $CurrentMonthDays[$year . "-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" .
        //             str_pad($i, 2, '0', STR_PAD_LEFT)] = [
        //             'view' => 0,
        //             'publisher_earnings' => 0,
        //             'referral_earnings' => 0,
        //         ];
        //     }

        //     foreach ($views_publisher as $view) {
        //         if (!$view['day']) {
        //             continue;
        //         }

        //         $day = $view['day'];
        //         $CurrentMonthDays[$day]['view'] = $view['count'];
        //         $CurrentMonthDays[$day]['publisher_earnings'] = $view['publisher_earnings'];
        //     }
        //     unset($view);
        //     foreach ($views_referral as $view) {
        //         if (!$view['day']) {
        //             continue;
        //         }

        //         $day = $view['day'];
        //         $CurrentMonthDays[$day]['referral_earnings'] = $view['referral_earnings'];
        //     }
        //     unset($view);

        //     if ((bool) get_option('cache_member_statistics', 1)) {
        //         Cache::write(
        //             'currentMonthDays_' . $auth_user_id . '_' . $date1 . '_' . $date2,
        //             $CurrentMonthDays,
        //             '15min'
        //         );
        //     }
        // }
        // $this->set('CurrentMonthDays', $CurrentMonthDays);

        // $this->set('total_views', array_sum(array_column_polyfill($CurrentMonthDays, 'view')));
        // $this->set('total_earnings', array_sum(array_column_polyfill($CurrentMonthDays, 'publisher_earnings')));
        // $this->set('referral_earnings', array_sum(array_column_polyfill($CurrentMonthDays, 'referral_earnings')));

        /*
        $popularLinks = Cache::read('popularLinks_' . $auth_user_id . '_' . $date1 . '_' . $date2, '15min');
        if ($popularLinks === false) {
            $popularLinks = $this->Users->Statistics->find()
                ->contain(['Links'])
                ->select([
                    'Links.alias',
                    'Links.url',
                    'Links.title',
                    'Links.domain',
                    'Links.created',
                    'views' => "count(case when Statistics.publisher_earn > 0 then Statistics.publisher_earn end)",
                    'publisher_earnings' => 'SUM(Statistics.publisher_earn)',
                ])
                ->where([
                    "Statistics.created BETWEEN :date1 AND :date2",
                    'Statistics.user_id' => $auth_user_id,
                ])
                ->order(['views' => 'DESC'])
                ->bind(':date1', $date1, 'datetime')
                ->bind(':date2', $date2, 'datetime')
                ->limit(10)
                ->group('Statistics.link_id')
                ->toArray();

            if ((bool)get_option('cache_member_statistics', 1)) {
                Cache::write('popularLinks_' . $auth_user_id . '_' . $date1 . '_' . $date2, $popularLinks, '15min');
            }
        }

        $this->set('popularLinks', $popularLinks);
        */

        $this->loadModel('Announcements');

        $announcements = $this->Announcements->find()
            ->where(['Announcements.published' => 1])
            ->order(['Announcements.id DESC'])
            ->limit(3)
            ->toArray();
        $this->set('announcements', $announcements);
    }

?>
