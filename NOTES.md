# PLAN

1. A user can create, view and delete saved searches.
    - We need a DB model to store search criteria for our searches: Table saved_search - with columns id, user_id, region, max_price, min_bedrooms, property_type, created_at, updated_at.
    - We need a page where we can manage the saved searches and a button to that page from listings page.
    - We need API for SavedSearches to create, view and delete the saved search
    - We should not allow the same user to save another search with identical criteria

2. When a new listing becomes live an alert is generated for that user.
    - we will use Listing model event, so that when listing becomes live or created live, then we generate saved search matching alerts.

3. A user can see their alerts.
    - we need paginated alerts page for the user and a button to access that page from listings page.
    - we need alerts DB model:
    - we need alerts API to show alerts on alerts page

4. We would run scheduled job to send notifications daily, so we dont spam users with a notification for every published listing.
    - we would include unsent alerts so if a scheduled run fails we can include them in the next run
    - we would only send a notification if the user has unsent alerts
    - each notification would include alerts about listing that where not sent before. We would mark alerts as sent after sending succeeds by setting the alert sent_at date.

Trade-offs / Deliberately left out
- Alert generation will run synchronously after the listing transaction commits, to keep this implementation runnable without a queue worker.
  The request creating or updating a property listing therefore will wait for a saved-search matching and alert creation to finish.
  We could dispatch async queue job after commit to avoid waiting, but as queue workers not required as part of the task this will be left out.
- Saved searches will require at least one filter: maximum price, minimum bedrooms, property type or region. This is to avoid matching every newly published listing.
- Listing and saved search "matching" will be tested with small datasets. At much larger scale, we could optimize saved searches table by adding indexes on criteria and use EXPLAIN and query timings on the database to verify their benefit.
- If a listing matches at least one saved search it is a match. It must match all criteria provided in that saved search.
- We would not backfill alerts. We would match only new listings, that are published after saved search is created.
- there is no way to make draft listings live using UI, we will have tests to prove alerts created when listing becomes live.
- System will create one alert per user and listing even if multiple saved searches match.
- To avoid spamming users, I assumed a daily notification would be a reasonable balance between limiting messages and letting buyers know about new properties before they miss an opportunity. Ideally, users could choose how often they receive notifications, as everyone has different preferences, but that was not a requirement.
- Daily notification schedule job is left out. For this task we only store alerts and show them in the app.
 
