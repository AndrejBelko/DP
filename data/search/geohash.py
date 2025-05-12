import pandas as pd
import mysql.connector
import json
import sys


def is_subsequence(pattern, sequence):
    """
    Checks whether all elements of 'pattern' appear in order within 'sequence'.

    This function determines if 'pattern' is a subsequence of 'sequence',
    meaning each element of 'pattern' appears in the same order in 'sequence',
    but not necessarily contiguously.

    Parameters:
        pattern (iterable): The list or sequence of elements to check as a subsequence.
        sequence (iterable): The sequence in which to look for the pattern.

    Returns:
        bool: True if 'pattern' is a subsequence of 'sequence', False otherwise.
    """
    it = iter(sequence)
    return all(p in it for p in pattern)


def runQuery(patterns, start_pos, end_pos, max_gap, min_match, dbName, type, user_id):
    """
    Executes a geohash pattern-matching query on the specified database to find matching tracks.

    Parameters:
        patterns (list of str): List of geohash codes (query pattern to search).
        start_pos (int): Minimum position (start index in pattern).
        end_pos (int): Maximum position (end index in pattern).
        max_gap (int): Maximum allowed gap (missing geohash steps) within a track.
        min_match (int): Minimum number of pattern matches required for a valid track.
        dbName (str): Name of the MySQL database to query.
        type (int): Map-matching type (0 = raw, 1 = matched).
        user_id (int): ID of the user whose data should be queried.

    Returns:
        pd.DataFrame: Filtered DataFrame with matching track information and metadata.
    """
    mydb = mysql.connector.connect(
        host="localhost",
        user="search",
        password="password",
        database=dbName
    )

    mycursor = mydb.cursor()
    mycursor.execute("CREATE TEMPORARY TABLE hladac (id INT NOT NULL AUTO_INCREMENT PRIMARY KEY, kod varchar(7)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4")
    mycursor.execute(
        "INSERT INTO hladac (kod) VALUES ('" + "'),('".join(patterns)+ "')"
    )
    mycursor.execute(
                f"select p.track_id as track, h.id as q_id from path p inner join hladac h on h.kod = p.geohash where p.mapmatched = {type} and p.user_id = {user_id} order by p.track_id, p.id; "
            )

    myresult = pd.DataFrame(mycursor.fetchall(), columns=['id', 'pos'])
    found = []
    for name, dr in myresult.groupby('id'):
        # prísnejšie vyhľadávanie, ak trajektória obsahuje jeden geohash reťazec viackrát, nevyhľadá ju
        if dr['pos'].is_monotonic_increasing:
            found.append([
                name, len(dr['pos'].unique()),
                dr['pos'].min(), dr['pos'].max(), max(dr['pos'].diff().max()-1,0),
                dr['pos'].unique().tolist()
            ])

        '''
        # vyhľadá trajektórie obsahujúce jeden geohash reťazec viackrát
        pattern_pos = list(range(1, len(patterns) + 1))
        if is_subsequence(pattern_pos, dr['pos'].tolist()):
            found.append([
                name, len(dr['pos'].unique()),
                dr['pos'].min(), dr['pos'].max(), max(dr['pos'].diff().max() - 1, 0),
                dr['pos'].tolist()
            ])
        '''


    r = pd.DataFrame(found, columns=['id', 'matches', 'start_pos', 'end_pos', 'max_gap',"path"]).sort_values(by=['matches', 'end_pos'],ascending=False)
    r = r.dropna()
    r = r[(r.matches>=min_match) & (r.start_pos<=start_pos) & (r.end_pos>=end_pos) & (r.max_gap<=max_gap) ]

    if len(r)==0:
        return pd.DataFrame([], columns=['id', 'matches', 'start_pos', 'end_pos', 'max_gap','track_id', 'path'])

    mycursor.execute(
        f"select track_id as id, track, timestamp, mapmatched from tracks where user_id = {user_id} and mapmatched = {type} and track_id in ({str(r['id'].values.tolist())[1:-1]});"
    )
    routes = pd.DataFrame(mycursor.fetchall(), columns=['id', 'track_id', 'timestamp', 'mapmatched'])
    mycursor.close()
    mydb.disconnect()

    routes['timestamp'] = routes['timestamp'].astype(str)

    # Ensure 'id' columns have the same type in both DataFrames
    r['id'] = r['id'].astype(str)  # Or use int if you prefer
    routes['id'] = routes['id'].astype(str)  # Same here, or use int

    # Now perform the merge
    r = r.merge(routes, on='id')

    # Continue with the rest of your code
    return r


if __name__ == '__main__':
    """
    Expected command-line arguments:
        sys.argv[1]: List of geohashes in string form, e.g., "['abc1234','bcd2345']"
        sys.argv[2]: Start position (int)
        sys.argv[3]: End position (int)
        sys.argv[4]: Max gap (int)
        sys.argv[5]: Minimum number of matches (int)
        sys.argv[6]: Database name (str)
        sys.argv[7]: Mapmatched type (0 or 1)
        sys.argv[8]: User ID (int)
    """

    # print(sys.argv[1][1:-1].split(","), sys.argv[2], sys.argv[3], sys.argv[4], sys.argv[5], sys.argv[6])
    r = runQuery(sys.argv[1][1:-1].split(","), int(sys.argv[2]), int(sys.argv[3]), int(sys.argv[4]), int(sys.argv[5]), sys.argv[6], int(sys.argv[7]), int(sys.argv[8]))

    print(json.dumps(r.values.tolist()))