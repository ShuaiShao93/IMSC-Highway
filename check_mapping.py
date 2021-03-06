import psycopg2
import Utils

if __name__ == '__main__':
    links_table = "staging_links"
    nodes_table = "staging_nodes"
    
    print "Connecting to database ......"
    conn_to = psycopg2.connect(host='osm-workspace-2.cfmyklmn07yu.us-west-2.rds.amazonaws.com', port='5432', database='osm', user='ds', password='928Sbi2sl')
    if conn_to:
        print "Connected."
    cursor = conn_to.cursor() 
    
    print "fetching mapping information from database"
    sql = "select road_name, direction, from_postmile, link_id, wayid,  start_nodeid, end_nodeid, sensor_id, on_edge_flag from ss_highway_mapping"
    cursor.execute(sql)
    results = cursor.fetchall()
    mapping = {}
    for road_name, direction, from_postmile, link_id, wayid, start_nodeid, end_nodeid, sensor_id, on_edge_flag in results:
        if int(road_name) == 10 and direction == 3:
            section = from_postmile/3
            if road_name not in mapping:
                mapping[road_name] = {}
            if direction not in mapping[road_name]:
                mapping[road_name][direction] = {}
            if section not in mapping[road_name][direction]:
                mapping[road_name][direction][section] = {}
            if link_id not in mapping[road_name][direction][section]:
                mapping[road_name][direction][section][link_id] ={}
                mapping[road_name][direction][section][link_id]['nodeid'] = (start_nodeid,end_nodeid)
                mapping[road_name][direction][section][link_id]['sensors'] = []
                mapping[road_name][direction][section][link_id]['wayid'] = wayid
                mapping[road_name][direction][section][link_id]['on_edge_flag'] = on_edge_flag
            if sensor_id:
                if sensor_id not in mapping[road_name][direction][section][link_id]['sensors']:
                    mapping[road_name][direction][section][link_id]['sensors'].append(sensor_id)
                    

    print "Connecting to database ......"
    his_conn_to = psycopg2.connect(host='v3-graph.cfmyklmn07yu.us-west-2.rds.amazonaws.com', port='5432', database='tallygo', user='ds', password='928Sbi2sl')
    if his_conn_to:
        print "Connected."
    his_cursor = his_conn_to.cursor()

    
    #fetching Historical
    print "fetching historical mapping"
    his_mapping = {}
    link_name = {}
    for road_name in mapping:
        for direction in mapping[road_name]:
            for section in mapping[road_name][direction]:
                print road_name, direction, section
                for link_id in mapping[road_name][direction][section]:
                    sql = "select name_default, from_node_id, to_node_id from "+ links_table+" where link_id = " + str(link_id)
                    cursor.execute(sql)
                    results = cursor.fetchall()
                    name_default, from_node_id, to_node_id = results[0]
                    link_name[link_id] = name_default
                        
                    sql = "select sensor_id from stg_line_on_edge_sensor_mapping where from_node_id ="+str(from_node_id)+"and to_node_id = "+str(to_node_id)
                    his_cursor.execute(sql)
                    results = his_cursor.fetchall()
                    for sensorl in results:
                        sensor_id = sensorl[0]
                        if link_id not in his_mapping:
                            his_mapping[link_id] = []
                        his_mapping[link_id].append(sensor_id)
    
    #fetching Yifan
    '''
    yf_mapping = {}
    link_name = {}
    for road_name in mapping:
        for direction in mapping[road_name]:
            for section in mapping[road_name][direction]:
                for link_id in mapping[road_name][direction][section]:
                    sql = "select sensor1_id,sensor2_id,sensor3_id from \"YC_SENSOR_MAPPING_ALL\" where link_id = "+str(link_id)
                    cursor.execute(sql)
                    results = cursor.fetchall()
                    for sensor1, sensor2, sensor3 in results:
                        if link_id not in yf_mapping:
                            yf_mapping[link_id] = []
                        if sensor1:
                            yf_mapping[link_id].append(sensor1)
                        if sensor2:
                            yf_mapping[link_id].append(sensor2)
                        if sensor3:
                            yf_mapping[link_id].append(sensor3)
    '''
    
    print "Comparing with historical"
    for road_name in mapping:
        for direction in mapping[road_name]:
            for section in mapping[road_name][direction]:
                for link_id in mapping[road_name][direction][section]:
                    if (link_id not in his_mapping and mapping[road_name][direction][section][link_id]['sensors'] != []) or (link_id in his_mapping and set(his_mapping[link_id]) != set(mapping[road_name][direction][section][link_id]['sensors'])):
                        '''
                        flag = False
                        for sensor_id in mapping[road_name][direction][section][link_id]: 
                            sql = "select * from highway_sensor_config where sensor_id =" + str(sensor_id)
                            cursor.execute(sql)
                            result = cursor.fetchall()
                            if len(result) == 0:
                                flag = True
                        if flag:
                            continue
                        '''
                        
                        
                        print '\n\n'
                        print "Road:", link_name[link_id]
                        print "Direction", direction
                        print "Section:", section
                        print "Link id:", link_id
                        print "Way id:", mapping[road_name][direction][section][link_id]['wayid']
                        
                        
                        from_node_id, to_node_id = mapping[road_name][direction][section][link_id]['nodeid']
                        
                        sql = "select ST_AsText(geom) from "+nodes_table+" where node_id = "+ str(from_node_id)
                        cursor.execute(sql)
                        from_node_loc = Utils.extract_loc_from_geometry(cursor.fetchall()[0][0])
                        
                        sql = "select ST_AsText(geom) from "+nodes_table+" where node_id = "+ str(to_node_id)
                        cursor.execute(sql)
                        to_node_loc = Utils.extract_loc_from_geometry(cursor.fetchall()[0][0])
                        
                        print "from_node:",from_node_loc[::-1],"to_node:", to_node_loc[::-1]
                        
                        print "\nMy mapped Sensors:", mapping[road_name][direction][section][link_id]['sensors']
                        print "My on_edge_flag:", mapping[road_name][direction][section][link_id]['on_edge_flag']
                        if link_id not in his_mapping:
                            print "Historical mapped Sensors:", []
                        else:
                            print "Historical mapped Sensors:", his_mapping[link_id]
                        
                        
                        print "\nMy sensor Information:"
                        for sensor_id in mapping[road_name][direction][section][link_id]['sensors']: 
                            sql = "select direction, onstreet, ST_AsText(start_lat_long) from highway_congestion_config where sensor_id = " + str(sensor_id)
                            cursor.execute(sql)
                            result = cursor.fetchall()[0]
                            sensor_dir = result[0]
                            onstreet = result[1]
                            sensor_loc = Utils.extract_loc_from_geometry(result[2])
                            print "sensor_id:",sensor_id,"direction:",sensor_dir,"onstreet:",onstreet,"loc:",sensor_loc[::-1]
                            
                        if link_id not in his_mapping:
                            print "Missing Mapping!!!!!!"
                            continue
                        
                        print "\nHistorical sensor Information:"
                        for sensor_id in his_mapping[link_id]: 
                            sql = "select direction, onstreet, ST_AsText(start_lat_long) from highway_congestion_config where sensor_id = " + str(sensor_id)
                            cursor.execute(sql)
                            result = cursor.fetchall()[0]
                            sensor_dir = result[0]
                            onstreet = result[1]
                            sensor_loc = Utils.extract_loc_from_geometry(result[2])
                            print "sensor_id:",sensor_id,"direction:",sensor_dir,"onstreet:",onstreet,"loc:",sensor_loc[::-1]
                            if road_name not in onstreet:
                                print "WRONG ROAD!!!!"
                            if int(sensor_dir) != int(direction):
                                print "WRONG DIRECTION!!!"
                            
                    else:
                        print "\nSame mapping on:",road_name,direction,section,link_id
                        print "My mapped Sensors:", mapping[road_name][direction][section][link_id]['sensors']
                        #print "Historical mapped Sensors:", his_mapping[link_id]
                        continue
                        
    '''           
    print "Comparing with Yifan"
    for road_name in mapping:
        for direction in mapping[road_name]:
            for section in mapping[road_name][direction]:
                for link_id in mapping[road_name][direction][section]:
                    if link_id in yf_mapping and mapping[road_name][direction][section][link_id] != yf_mapping[link_id]:
                        
                        sql = "select name_default, from_node_id, to_node_id from links where link_id = " + str(link_id)
                        cursor.execute(sql)
                        results = cursor.fetchall()
                        name_default, from_node_id, to_node_id = results[0]
                        
                        print '\n\n'
                        print "Road:", name_default
                        print "Direction", direction
                        print "Section:", section
                        print "Link id:", link_id
                        
                        sql = "select ST_AsText(geom) from nodes where node_id = "+ str(from_node_id)
                        cursor.execute(sql)
                        from_node_loc = Utils.extract_loc_from_geometry(cursor.fetchall()[0][0])
                        
                        sql = "select ST_AsText(geom) from nodes where node_id = "+ str(to_node_id)
                        cursor.execute(sql)
                        to_node_loc = Utils.extract_loc_from_geometry(cursor.fetchall()[0][0])
                        
                        print "from_node:",from_node_loc[::-1],"to_node:", to_node_loc[::-1]
                        
                        print "\nMy mapped Sensors:", mapping[road_name][direction][section][link_id]
                        if len(yf_mapping[link_id]) == 0:
                            print "YF mapped Sensors:", []
                        else:
                            print "YF mapped Sensors:", yf_mapping[link_id]
                        
                        
                        print "My sensor Information:"
                        for sensor_id in mapping[road_name][direction][section][link_id]: 
                            sql = "select direction, onstreet, ST_AsText(start_lat_long) from highway_congestion_config where sensor_id = " + str(sensor_id)
                            cursor.execute(sql)
                            result = cursor.fetchall()[0]
                            sensor_dir = result[0]
                            onstreet = result[1]
                            sensor_loc = Utils.extract_loc_from_geometry(result[2])
                            print "sensor_id:",sensor_id,"direction:",sensor_dir,"onstreet:",onstreet,"loc:",sensor_loc[::-1]
                            
                        if len(yf_mapping[link_id]) == 0:
                            print "Missing Mapping!!!!!!"
                            continue
                        
                        print "\nYF sensor Information:"
                        for sensor_id in yf_mapping[link_id]: 
                            sql = "select direction, onstreet, ST_AsText(start_lat_long) from highway_congestion_config where sensor_id = " + str(sensor_id)
                            cursor.execute(sql)
                            result = cursor.fetchall()[0]
                            sensor_dir = result[0]
                            onstreet = result[1]
                            sensor_loc = Utils.extract_loc_from_geometry(result[2])
                            print "sensor_id:",sensor_id,"direction:",sensor_dir,"onstreet:",onstreet,"loc:",sensor_loc[::-1]
                            if road_name not in onstreet:
                                print "WRONG ROAD!!!!"
                            if int(sensor_dir) != int(direction):
                                print "WRONG DIRECTION!!!"
                            
                    else:
                        #print "\nSame mapping on:",road_name,direction,section,link_id
                        #print "My mapped Sensors:", mapping[road_name][direction][section][link_id]
                        #print "Historical mapped Sensors:", his_mapping[link_id]
                        continue
    '''