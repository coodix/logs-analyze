MongoDb was selected as a DB where Ours service contains JSON logs according to the task description.

There is no HTTP endpoint where JSON log entry can be added in the solution. It's assumed that DB with logs is given and logs are stored in "logs.logs" collection. Playing with JSON logs can be done via mongo-expredss web interface available at http://localhost:8081/.


1. git clone git@github.com:coodix/logs-analyze.git logs-analyze
2. cd logs-analyze
3. docker-compose up
4. Initialize database with test data by visiting HTTP endpoint in browser: http://localhost:8000/seed_with_test_data
5. List of users and theirs partner links is available by URL: http://localhost:8000/referrals

The algorithm building the list of users of Ours service:

1. Get list of documents where each document represent one user together with related checkouts.
Implemented by executing mapReduce:
1.1 Map stage: the grouping key is client_id
1.2 Reduce: analyze user's visits and build list of checkouts

2. Build a collection of checkouts by executing aggregation pipeline. The result is stored in "logs.checkouts" collection.

3. Select checkouts made by users came from Ours service and group them showing each users with related urls.

In current solution building of checkouts collection is implemented right in the HTTP handler, but under real load it makes sense to move it to background.
