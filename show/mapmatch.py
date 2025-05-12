import sys, csv, requests, json
from os import path
import gpxpy
import subprocess
import re
import os
import config

## Save map matched data to a csv file
def create_file_for_db(data, track, user):
    """
    Creates a CSV file from map-matched GPS data for a given user and track name.

    Parameters:
        data (list): List of [longitude, latitude] pairs.
        track (str): Name or identifier for the track.
        user (str): Username or user identifier.

    Returns:
        str: Full path to the created CSV file.
    """
    base_folder = f"/home/data/import/files/mapmatched/{user}"

    if not os.path.isdir(base_folder):
        os.makedirs(base_folder)

    file_path = path.join(base_folder, track + '.csv')
    file = open(file_path,'w')
    file.write("track,latitude,longitude")
    for i in range(len(data)):
        file.write("\n"+track+","+str(data[i][1])+","+str(data[i][0]))
    file.close()
    return file_path

## Decode polyline
def decode_polyline(polyline):
    """
    Decodes a polyline string (Google Encoded Polyline Algorithm) into a list of coordinates.

    Parameters:
        polyline (str): Encoded polyline string.

    Returns:
        list: List of [longitude, latitude] pairs.
    """
    points = []
    index = lat = lng = 0

    while index < len(polyline):
        result = 1
        shift = 0
        while True:
            b = ord(polyline[index]) - 63 - 1
            index += 1
            result += b << shift
            shift += 5
            if b < 0x1F:
                break
        lat += (~result >> 1) if (result & 1) != 0 else (result >> 1)

        result = 1
        shift = 0
        while True:
            b = ord(polyline[index]) - 63 - 1
            index += 1
            result += b << shift
            shift += 5
            if b < 0x1F:
                break
        lng += ~(result >> 1) if (result & 1) != 0 else (result >> 1)
        points.append([round(lng * 1e-6, 6), round(lat * 1e-6, 6)])
    return points

## Map match the points
def map_match(points, container_name, parameters, costing):
    """
    Sends a request to Valhalla's map-matching API with given GPS points.

    Parameters:
        points (list): List of [longitude, latitude] pairs to map-match.
        container_name (str): Docker container name running Valhalla.
        parameters (dict): Additional map-matching parameters (e.g., search radius, accuracy).
        costing (str): Travel mode for map matching ("auto", "pedestrian", etc.).

    Returns:
        tuple: ("SUCESS;", encoded geometry string) if successful, or ("Error;", {}) on failure.
    """
    strr = ""
    for pt in points:
        strr += '{"lat":'+str(pt[1])+',"lon":'+str(pt[0])+'},'
    strr = strr[:-1]

    meili_coordinates = strr
    meili_head = '{"shape":['
    meili_tail = '], "shape_match":"map_snap", "costing": "' + costing + '", "costing_options":{"pedestrian":{"ignore_access":true} }, "format":"osrm", "trace_options":{"search_radius": ' + \
                 parameters['search_radius'] + ', "turn_penalty_factor": ' + parameters['turn_penalty_factor'] + ', "gps_accuracy": ' + parameters['gps_accuracy'] + '}}'
    meili_request_body = meili_head + meili_coordinates + meili_tail
    port = config.VALHALLA_PORT
    url = f"http://{container_name}:{port}/trace_route"
    headers = {'Content-type': 'application/json'}
    data = str(meili_request_body)
    try:
        r = requests.post(url, data=data, headers=headers, timeout=10)
        response_text = json.loads(r.text)
        return "SUCESS;", response_text['matchings'][0]['geometry']
    except Exception as e:
        return "Error;Request did not succeed.", {}

## Iterative map match that skips unmatchable parts
def split_and_map_match(points, container_name, parameters, costing, chunk_size=50, min_successful=3):
    """
    Splits GPS points into chunks and performs map matching for each chunk individually.

    Parameters:
        points (list): List of [longitude, latitude] pairs.
        container_name (str): Docker container name running Valhalla.
        parameters (dict): Map-matching configuration parameters.
        costing (str): Travel mode.
        chunk_size (int): Maximum number of points per chunk.
        min_successful (int): Minimum number of matched points to accept result.

    Returns:
        list or None: List of matched [lon, lat] points, or None if too few matches.
    """
    all_matched_points = []
    i = 0
    while i < len(points):
        chunk = points[i:i + chunk_size]
        status, geometry = map_match(chunk, container_name, parameters, costing)
        if status.startswith("SUCESS"):
            matched = decode_polyline(geometry)
            all_matched_points.extend(matched)
            i += chunk_size
        else:
            i += 1
    return all_matched_points if len(all_matched_points) >= min_successful else None

## Remove any letters from lat/lon
def clean_value(value):
    """
    Cleans a coordinate string value by removing non-numeric characters.

    Parameters:
        value (str): Input coordinate string.

    Returns:
        str: Cleaned numeric string.
    """
    return re.sub(r'[^0-9.-]', '', value)

## Get points from csv file
def load_points(filename):
    """
    Extracts coordinates from a CSV file by detecting latitude and longitude columns.

    Parameters:
        filename (str): Path to the CSV file.

    Returns:
        list or None: List of [longitude, latitude] pairs or None if columns not found.
    """
    with open(filename, 'r', newline='') as csvfile:
        spamreader = csv.reader(csvfile, delimiter=',')
        header = next(spamreader)
        lat_index = None
        lon_index = None
        for i, col in enumerate(header):
            if 'lat' in col.lower():
                lat_index = i
            elif 'lon' in col.lower():
                lon_index = i
        if lat_index is None or lon_index is None:
            return None
        coords = []
        for row in spamreader:
            lat_value = clean_value(row[lat_index])
            lon_value = clean_value(row[lon_index])
            coords.append([float(lon_value), float(lat_value)])
        return coords

def load_points_from_geojson(file):
    """
    Extracts coordinates from the first geometry feature in a GeoJSON file.

    Parameters:
        file (str): Path to the GeoJSON file.

    Returns:
        list: List of [longitude, latitude] pairs.
    """
    f = open(file, "r")
    text = f.read()
    data = json.loads(text)
    features = data['features']
    feature = features[0]
    geometry = feature['geometry']
    coordinates = geometry['coordinates']
    f.close()
    return  coordinates

def load_gpx_points(file):
    """
    Parses GPX file and extracts all track point coordinates.

    Parameters:
        file (str): Path to the GPX file.

    Returns:
        list: List of [longitude, latitude] pairs.
    """
    points = []
    with open(file, 'r') as gpx_file:
        gpx = gpxpy.parse(gpx_file)
        for track in gpx.tracks:
            for segment in track.segments:
                for point in segment.points:
                    points.append([point.longitude, point.latitude])
    return points

def folder_process(params):
    """
    Processes input track file (CSV, GeoJSON, or GPX), performs map matching, and stores the result.

    Parameters:
        params (dict): Input parameters including file path, container name, user info, and matching settings.

    Returns:
        str: Path to the saved map-matched CSV file or "None" if matching failed.
    """
    container_name = params["container"]
    user = params["username"]
    file_input = params["file"].replace(r'\/', '/')
    parameters = params["parameters"]

    successful = []
    failed = {}
    retDict = {}

    format = os.path.splitext(file_input)[1]
    name = file_input[:str(file_input).find(".")]
    name = os.path.basename(name)

    points = None
    if format.lower() == ".csv":
        points = load_points(file_input)
    elif format.lower() == ".geojson":
        points = load_points_from_geojson(file_input)
    elif format.lower() == ".gpx":
        points = load_gpx_points(file_input)
    else:
        failed[name] = f"Points couldn't be extracted."

    if points is None:
        failed[name] = f"Points couldn't be extracted."

    subdir = parameters['type']
    if subdir == "Walk":
        matched_pts = split_and_map_match(points, container_name, parameters, costing="pedestrian")
    elif subdir == "Drive":
        matched_pts = split_and_map_match(points, container_name, parameters, costing="auto")
    else:
        matched_pts = None

    path = "None"
    if matched_pts:
        path = create_file_for_db(matched_pts, name, user)
        successful.append(name)
    else:
        failed[name] = "No valid segments found for map matching."

    retDict["failed"] = len(failed)
    retDict["successful"] = len(successful)
    retDict["failed_info"] = failed

    print(json.dumps(retDict))
    return path

def check_valhalla_connection(container_name, port=config.VALHALLA_PORT):
    """
    Checks whether the Valhalla container is reachable and responsive.

    Parameters:
        container_name (str): Docker container name.
        port (int): Port on which Valhalla is running.

    Returns:
        bool: True if connection is successful, False otherwise.
    """
    url = f"http://{container_name}:{port}/status"
    try:
        response = requests.get(url, timeout=5)
        if response.status_code == 200:
            return True
        else:
            return False
    except requests.exceptions.RequestException as e:
        print(f"Connection to Valhalla failed: {e}")
        return False

params = json.loads(sys.argv[1])
if check_valhalla_connection(params["container"]):
    path = folder_process(params)
    if path == "None":
        sys.exit(1)
    else:
        subprocess.run(["python3", "/var/www/html/track_to_database.py", params['filename'], path, params['username'], params['user_id'], '1', params['parameters']['type'], params['track_id']])
        sys.exit(0)
else:
    sys.exit(1)

