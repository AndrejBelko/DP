import sys, os, csv, requests, json
from os import path
from os.path import exists
import gpxpy
import base64
import subprocess
import re
import os

## Save map matched data to a csv file
def create_file_for_db(data,track,user,type,name):
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
def map_match(points,container_name,parameters,costing):
    strr = ""
    for pt in points:
        strr += '{"lat":'+str(pt[1])+',"lon":'+str(pt[0])+'},'
    strr = strr[:-1]

    meili_coordinates = strr
    meili_head = '{"shape":['
    meili_tail = '], "shape_match":"map_snap", "costing": "' + costing + '", "costing_options":{"pedestrian":{"ignore_access":true} }, "format":"osrm", "trace_options":{"search_radius": ' + \
                 parameters['search_radius'] + ', "turn_penalty_factor": ' + parameters['turn_penalty_factor'] + ', "gps_accuracy": ' + parameters['gps_accuracy'] + '}}'
    meili_request_body = meili_head + meili_coordinates + meili_tail
    port = 8002
    url = f"http://{container_name}:{port}/trace_route"
    headers = {'Content-type': 'application/json'}
    data = str(meili_request_body)
    try:
        r = requests.post(url, data=data, headers=headers)
        response_text = json.loads(r.text)
        return "SUCESS;", response_text['matchings'][0]['geometry']
    except:
        return "Error;Request did not succeed.", json.loads(r.text)

## Iterative map match that skips unmatchable parts
def split_and_map_match(points, container_name, parameters, costing, chunk_size=50, min_successful=3):
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
    return re.sub(r'[^0-9.-]', '', value)

## Get points from csv file
def load_points(filename):
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
    points = []
    with open(file, 'r') as gpx_file:
        gpx = gpxpy.parse(gpx_file)
        for track in gpx.tracks:
            for segment in track.segments:
                for point in segment.points:
                    points.append([point.longitude, point.latitude])
    return points

def folder_process(params):
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

    if format.lower() == ".csv":
        points = load_points(file_input)
    elif format.lower() == ".geojson":
        points = load_points_from_geojson(file_input)
    elif format.lower() == ".gpx":
        points = load_gpx_points(file_input)
    else:
        failed[name] = f"Points couldn't be extracted."

    if points == None:
        failed[name] = f"Points couldn't be extracted."

    geometry = None
    subdir = parameters['type']
    if subdir == "Walk":
        matched_pts = split_and_map_match(points, container_name, parameters, costing="pedestrian")
    elif subdir == "Drive":
        matched_pts = split_and_map_match(points, container_name, parameters, costing="auto")
    else:
        matched_pts = None

    if matched_pts:
        path = create_file_for_db(matched_pts, name, user, subdir, "map-match.csv")
        successful.append(name)
    else:
        failed[name] = "No valid segments found for map matching."

    retDict["failed"] = len(failed)
    retDict["successful"] = len(successful)
    retDict["failed_info"] = failed

    print(json.dumps(retDict))
    return path

params = json.loads(sys.argv[1])
path = folder_process(params)
subprocess.run(["python3", "/var/www/html/track_to_database.py", params['filename'], path, params['username'], params['user_id'], '1', params['parameters']['type'], params['track_id']])