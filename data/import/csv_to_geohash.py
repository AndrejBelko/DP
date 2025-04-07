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
import os

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


def insertUser(dbname):
    mydb = mysql.connector.connect(
            host="localhost",
            user="search",
            database="hashcode",
            password="password"
        )
    mycursor = mydb.cursor()
    sql = """INSERT INTO users (username)
                 VALUES (%s)"""

        # Sample data (replace with real values)
    user_data = (dbname,)

    # Execute the query
    mycursor.execute(sql, user_data)

    # Commit the changes
    mydb.commit()

    # SQL query to select the ID where username = dbname
    sql = "SELECT id FROM users WHERE username = %s"
    mycursor.execute(sql, (dbname,))

    # Fetch the result
    result = mycursor.fetchone()

    user_id = result[0]  # Extract the ID from the tuple

    mycursor.close()
    mydb.disconnect()

    return user_id


def getTrackId(user_id):
    mydb = mysql.connector.connect(
                host="localhost",
                user="search",
                database="hashcode",
                password="password"
            )
    mycursor = mydb.cursor()

    # Get max track_id for the user
    sql = "SELECT MAX(track_id) FROM path WHERE user_id = %s"
    mycursor.execute(sql, (user_id,))
    result = mycursor.fetchone()

    # Handle case where user has no previous tracks
    track_id = (result[0] + 1) if result[0] is not None else 1

    # Cleanup
    mycursor.close()
    mydb.disconnect()  # Fixed disconnect()

    return track_id


def csvToGeohash(csvPath, dbName, title, directory_path):
    user_id = insertUser(dbName)
    track_id = getTrackId(user_id)
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
    dx1 = pd.DataFrame(geohash, columns=["user_id", "geohash", "track_id", "mapmatched", "type", "timestamp", "length"])
    if not os.path.isdir(directory_path):
            os.makedirs(directory_path)
    dx1.to_csv(f"{directory_path}/{dbName}_path.csv", sep=';', index=False, mode="w")
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
                user_id,
                gh.encode(row_data['latitude'], row_data['longitude'], precision=7),  # Generate geohash
                track_id,  # Track ID
                0,  # Mapmatched flag (placeholder)
                "Drive",
                timestamp,  # Current timestamp
                trajectory_length  # Total track length
            ])
            row += 1

        # Write to file in batches
        if row > 1000000:
            dx1 = pd.DataFrame(geohash, columns=["user_id", "geohash", "track_id", "mapmatched", "type", "timestamp", "length"])
            dx1.to_csv(f"{directory_path}/{dbName}_path.csv", header=False, sep=';', index=False, mode="a")
            row = 0
            geohash = []
            total += 1

    dx1 = pd.DataFrame(geohash, columns=["user_id", "geohash", "track_id", "mapmatched", "type","timestamp", "length"])
    dx1.to_csv(f"{directory_path}/{dbName}_path.csv", header=False, sep=';', index=False, mode="a")

    #track.csv
    tracks = []
    dx2 = pd.DataFrame(tracks, columns=['user_id', 'track_id', 'filename', 'track', 'mapmatched', "type",  "timestamp", "length"])
    dx2.to_csv(f"{directory_path}/{dbName}_track.csv", index=False, sep=';', quoting=csv.QUOTE_NONE, mode="w")
    row = 0
    total = 0
    grouped = df.groupby('track')
    print("Generating geojsons for "+str(len(grouped))+" tracks into track.csv ...")
    for id, values in grouped:
        trajectory_length = haversine_vectorized(np.array(values['latitude']), np.array(values['longitude']))
        tracks.append([user_id, track_id, dbName, str(geojson.Feature(geometry=geojson.LineString(values[["longitude", "latitude"]].values.tolist()))), 0, "Drive", timestamp, trajectory_length])
        row+=1
        if row>10000:
            dx2 = pd.DataFrame(tracks, columns=['user_id', 'track_id', 'filename', 'track', 'mapmatched', "type",  "timestamp", "length"])
            dx2.to_csv(f"{directory_path}/{dbName}_track.csv", header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")
            row = 0
            tracks = []
            total +=1
            print(str(total*10)+"k tracks done...")

    dx2 = pd.DataFrame(tracks, columns=['user_id', 'track_id', 'filename', 'track', 'mapmatched', "type",  "timestamp", "length"])
    dx2.to_csv(f"{directory_path}/{dbName}_track.csv", header=False, index=False, sep=';', quoting=csv.QUOTE_NONE, mode="a")

    print("Generating db info...")
    #latlon_median.csv
    mapconfig = {"center":{"lat": df["latitude"].median(), "lon": df["longitude"].median()}, "title": title, "dbname": dbName, "attribution": ""}
    with open(f"/var/www/html/center/{dbName}.json", 'w') as outfile:
        outfile.write(json.dumps(mapconfig))

    return dx1, dx2

def importData(dx1, dx2, dbName, directory_path):
    print("creating database...")
    mydb = mysql.connector.connect(
        host="localhost",
        user="root",
        password="root",
        database="hashcode",
        allow_local_infile=True  # Ensure that local_infile is enabled
    )
    mycursor = mydb.cursor()

    sql = """
    INSERT INTO path (user_id, geohash, track_id, mapmatched, type, timestamp, length)
    VALUES (%s, %s, %s, %s, %s, %s, %s)
    """

    # Prepare Data
    data = list(dx1[['user_id', 'geohash', 'track_id', 'mapmatched', 'type', 'timestamp', 'length']].itertuples(index=False, name=None))

    # Execute Batch Insert
    mycursor.executemany(sql, data)
    mydb.commit()

    sql = """
    INSERT INTO tracks (user_id, track_id, filename, track, mapmatched, type, timestamp, length)
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
    """

    # Prepare Data
    data = list(dx2[['user_id', 'track_id', 'filename', 'track', 'mapmatched', 'type', 'timestamp', 'length']].itertuples(index=False, name=None))

    # Execute Batch Insert
    mycursor.executemany(sql, data)
    mydb.commit()

    
if __name__ == '__main__':
    if len(sys.argv)<3:
        print("Specify path to CSV and dbName")
    else:
        title = sys.argv[2] if len(sys.argv)==3 else sys.argv[3]
        directory_path = f"/home/data/import/files/db/{sys.argv[2]}"
        dx1, dx2 = csvToGeohash(sys.argv[1], sys.argv[2], title, directory_path)
        importData(dx1, dx2, sys.argv[2], directory_path)
        print("Finished")
        subprocess.run(["python3", "/var/www/html/geohash_area.py", sys.argv[2]])