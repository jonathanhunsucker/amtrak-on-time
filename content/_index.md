---
---

This site catalogues and analyzes historical Amtrak train data, including arrival time variance beyond Amtrak's public-facing on-time performance metric.

## Find train arrival time insight
- [By route](/routes)
- [By train number](/trains)

## Understanding the on-time performance metric
Amtrak considers their trains on-time if they arrive within a threshold of time [^bts]. The threshold varies by the route's distance. Longer routes can arrive later and still be "on-time". 

| Trip length (miles) | Threshold (minutes) |
|------|-----|
| 0 to 250 | 10 or less |
| 251 to 350 | 15 or less |
| 351 to 450 | 20 or less |
| 451 to 550 | 25 or less |
| Over 551 | 30 or less |

[^bts]: [Amtrak On-Time Performance Trends and Hours of Delay by Cause](https://www.bts.gov/content/amtrak-time-performance-trends-and-hours-delay-cause), Bureau of Transportation Statistics

This metric is a [percentile rank](https://en.wikipedia.org/wiki/Percentile_rank), whose score depends on the trip length. Percentile ranks are useful when the score is relevant to your needs. For example, when "10 minutes or less" is the difference between making or missing a connection, an important meeting, etc.

If you need stronger guarantees about arrival times, then a single percentile rank won't cut it.

## Comparison with this site's visualizations
The natural extension of a single percentile rank, is multiple ranks. Each train's page includes the median and 90th percentile of arrival delay. Take Acela Express' Train 2203, which departs New York Penn Station at 8:00a every weekday:

<blockquote>{{< train/timeliness 2203 >}}</blockquote>

The next extension of percentile ranks, is a probability distribution. Each train's page also includes a bar graph displaying the distribution of arrival times. Take Train 2110 for example. It's scheduled to arrive at 1:50p:

<blockquote>{{< train/arrival-delay-distribution 2110 >}}</blockquote>

Curious about your own route? <a href="/routes">Dive in</a>.
