import pandas as pd
import libgeohash as gh
from sqlalchemy import create_engine
import geojson
import csv
import mysql.connector
import sys
from datetime import datetime
import json
import subprocess
import numpy as np

def haversine_vectorized(latitudes, longitudes):
    R = 6371000  # Earth radius in meters
    lat1 = np.radians(latitudes[:-1])
    lat2 = np.radians(latitudes[1:])
    lon1 = np.radians(longitudes[:-1])
    lon2 = np.radians(longitudes[1:])

    d_phi = lat2 - lat1
    d_lambda = lon2 - lon1

    a = np.sin(d_phi / 2.0)**2 + np.cos(lat1) * np.cos(lat2) * np.sin(d_lambda / 2.0)**2
    c = 2 * np.arctan2(np.sqrt(a), np.sqrt(1 - a))
    return np.sum(R * c)

def csvToGeohash(csvPath, dbName, title):
    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
    print("reading dataset ...")
    df = pd.read_csv(csvPath)
    df = df.fillna('')
    df = df[df['uid'] == 0] #doplnene
    print("loaded dataset of "+str(len(df))+ " lines")
    df.rename(columns={'tid': 'track','lng': 'longitude','lat': 'latitude'}, inplace=True)
    #path.csv
    lat_col = df.columns.get_loc("latitude")
    lon_col = df.columns.get_loc("longitude")
    track_col = df.columns.get_loc("track")
    geohash = []
    dx = pd.DataFrame(geohash, columns=["geohash","track", "mapmatched", "timestamp", "length"])
    dx.to_csv("/home/data/import/files/"+dbName+"_path.csv", index=False, mode="w")
    row = 0
    total = 0
    print(track_col)
    print("Encoding tracks to geohash sequences into path.csv... ")
    grouped = df.groupby('track')
    for track_id, values in grouped:
        # Extract latitudes and longitudes for the track
        latitudes = values['latitude'].to_numpy()
        longitudes = values['longitude'].to_numpy()

        # Calculate trajectory length
        trajectory_length = haversine_vectorized(latitudes, longitudes)

        # Add rows for each point in the track
        for _, row_data in values.iterrows():
            geohash.append([
                gh.encode(row_data['latitude'], row_data['longitude'], precision=7),  # Generate geohash
                track_id,  # Track ID
                0,  # Mapmatched flag (placeholder)
                timestamp,  # Current timestamp
                trajectory_length  # Total track length
            ])
            row += 1

        # Write to file in batches
        if row > 1000000:
            dx = pd.DataFrame(geohash, columns=["geohash", "track", "mapmatched", "timestamp", "length"])
            dx.to_csv(f"/home/data/import/files/{dbName}_path.csv", header=False, index=False, mode="a")
            row = 0
            geohash = []
            total += 1

    dx = pd.DataFrame(geohash, columns=["geohash","track", "mapmatched","timestamp", "length"])
    dx.to_csv("/home/data/import/files/"+dbName+"_path.csv", header=False, index=False, mode="a")

    #track.csv
    tracks = []
    dx = pd.DataFrame(tracks, columns=['route', 'track', "mapmatched", "timestamp", "length"])
    dx.to_csv("/home/data/import/files/"+dbName+"_track.csv", index=False, sep=';', quoting=csv.QUOTE_NONE, mode="w")
    row = 0
    total = 0
    grouped = df.groupby('track')
    print("Generating geojsons for "+str(len(grouped))+" tracks into track.csv ...")
    for id, values in grouped:
        trajectory_length = haversine_vectorized(np.array(values['latitude']), np.array(values['longitude']))
        tracks.append([id, str(geojson.Feature(geometry=geojson.LineString(values[["longitude", "latitude"]].values.tolist()))), 0, timestamp, trajectory_length])
        row+=1
        if row>10000:
            dx = pd.DataFrame(tracks, columns=['route', 'track', "mapmatched", "timestamp", "length"])
            dx.to_csv("/home/data/import/files/"+dbName+"_track.csv", header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")
            row = 0
            tracks = []
            total +=1
            print(str(total*10)+"k tracks done...")

    dx = pd.DataFrame(tracks, columns=['route', 'track', "mapmatched", "timestamp", "length"])
    dx.to_csv("/home/data/import/files/"+dbName+"_track.csv", header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")

    print("Generating db info...")
    #latlon_median.csv
    mapconfig = {"center":{"lat": df["latitude"].median(), "lon": df["longitude"].median()}, "title": title, "dbname": dbName, "attribution": ""}
    with open("/var/www/html/center/"+dbName+".json", 'w') as outfile:
        outfile.write(json.dumps(mapconfig))

def importData(dbName):
    print("creating database...")
    mydb = mysql.connector.connect(
        host="localhost",
        user="search",
        password="password"
    )
    mycursor = mydb.cursor()
    mycursor.execute("CREATE DATABASE IF NOT EXISTS `"+dbName+"` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;")
    mycursor.close()
    mydb.disconnect()

    mydb = mysql.connector.connect(
        host="localhost",
        user="root",
        password="root",
        database=dbName,
        allow_local_infile=True
    )
    mycursor = mydb.cursor()
    mycursor.execute("DROP TABLE IF EXISTS `path`;")
    mycursor.execute("""
        CREATE TABLE `path` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `hash` varchar(7) NOT NULL,
            `track` varchar(80) NOT NULL,
            `mapmatched` varchar(80) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL,
            `length` FLOAT NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ihash` (`hash`)
        ) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4;
    """)
    mycursor.execute("DROP TABLE IF EXISTS `tracks`;")
    mycursor.execute("""
        CREATE TABLE `tracks` (
            `route` varchar(250) NOT NULL,
            `track` mediumtext NOT NULL,
            `mapmatched` varchar(80) NOT NULL,
            `timestamp` TIMESTAMP NOT NULL,
            `length` FLOAT NOT NULL,
            PRIMARY KEY (`route`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    """)
    mycursor.execute("SET GLOBAL local_infile=1;")
    print("importing tracks ...")
    mycursor.execute("load data local infile '/home/data/import/files/"+dbName+"_path.csv' into table `path` fields terminated by ',' lines terminated by '\n' ignore 1 lines (`hash`, `track`, `mapmatched`,`timestamp`,`length`);")
    print("importing geojsons ...")
    mycursor.execute("load data local infile '/home/data/import/files/"+dbName+"_track.csv' into table `tracks` fields terminated by ';' lines terminated by '\n' ignore 1 lines (`route`, `track`, `mapmatched`,`timestamp`, `length`);")
    mydb.commit()
    mycursor.close()
    mydb.disconnect()

    
if __name__ == '__main__':
    if len(sys.argv)<3:
        print("Specify path to CSV and dbName")
    else:
        title = sys.argv[2] if len(sys.argv)==3 else sys.argv[3]
        csvToGeohash(sys.argv[1], sys.argv[2], title)
        importData(sys.argv[2])
        print("Finished")
        subprocess.run(["python3", "/var/www/html/geohash_area.py", sys.argv[2]])