import pandas as pd
import mysql.connector
import json
import sys

def runQuery(dbName, type):
    mydb = mysql.connector.connect(
        host="localhost",
        user="search",
        password="password",
        database=dbName
    )

    mycursor = mydb.cursor()

    mycursor.execute(
        f"select id, track, mapmatched, type, timestamp, length from tracks where mapmatched = {type};"
    )
    routes = pd.DataFrame(mycursor.fetchall(), columns=['id', 'route','mapmatched', 'type', 'timestamp','length'])
    mycursor.close()
    mydb.disconnect()

    return routes

if __name__ == '__main__':

    # print(sys.argv[1][1:-1].split(","), sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6])
    r = runQuery(sys.argv[1], int(sys.argv[2]))
    r = r.applymap(lambda x: x.isoformat() if isinstance(x, pd.Timestamp) else x)
    print(json.dumps(r.values.tolist()))