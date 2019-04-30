
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">

    <title>Tides v2.0</title>

	<!-- Latest compiled and minified CSS -->
	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" >
	<!-- Latest compiled and minified JavaScript -->
	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

</head>
<body>
<?php
// Check for zip code parameter
if (isset($_GET['zip'])) {
        if (filter_var($_GET['zip'], FILTER_SANITIZE_SPECIAL_CHARS)) {
            $zip = $_GET['zip'];
        }
    }else {
		$zip = null;
	}
if (isset($_GET['flush'])) {
        if (filter_var($_GET['flush'], FILTER_SANITIZE_SPECIAL_CHARS)) {
            $flush = $_GET['flush'];
        }
    }else {
		$flush = false;
	}
// Include the required Class file
include('PAI_Tide.php');
$tide = new PAI_Tide;
$tideversion = $tide::version;
$data = json_decode($tide->getTide($zip,$flush));
?>

    <!-- Page Content -->
    <div class="container">

        <!-- Page Header -->
        <div class="row">
            <div class="col-lg-12">
                <h1 class="page-header">Tides
					<?php
					echo ("<small>{$data->Response->Port->port}\n v{$tideversion}</small>\n");
					?>
                </h1>
            </div>
        </div>
        <!-- /.row -->

        <!-- Projects Row -->
        <div class="row">
            <div class="col-md-4 portfolio-item">
                <h3>
                    <a href="#">Tides</a>
                </h3>
				<?php
				echo ("<table border='1' width='90%' >\n");
				echo ("<tr><th>When</th><th>Type</th><th>Height</th></tr>\n");
				//now echo to table
				foreach($data->Response->Tide as $item) {
					echo("<tr>\n");
					$d = gmdate('M-d D H:i',$item->twhen-(5*3600)); //adj to correct tide data
					echo ("<td>{$d}</td>\n");
					echo("<td>{$item->what}</td>\n");
					echo("<td>{$item->feet}</td>\n");
					echo("</tr>\n");
				}
				?>
				</table>
            </div>
            <div class="col-md-4 portfolio-item">
                <h3>
                    <a href="#">Sun/Moon</a>
                </h3>
 				<?php
				echo ("<table border='1' width='90%' >\n");
				echo ("<tr><th>When</th><th>Type</th></tr>\n");
				//now echo to table
				foreach($data->Response->Sun as $item) {
					echo("<tr>\n");
					$d = date('M-d D H:i',$item->swhen);
					echo ("<td>{$d}</td>\n");
					echo("<td>{$item->what}</td>\n");
					echo("</tr>\n");
				}
				?>
				</table>
            </div>
            <div class="col-md-4 portfolio-item">
                <h3>
                    <a href="#">Forecast</a>
                </h3>
				<?php
				echo ("<table border='1' width='90%' >\n");
				echo ("<tr><th>When</th><th>Forecast</th></tr>\n");
				//now echo to table
				foreach($data->Response->Fcst as $item) {
					echo("<tr>\n");
					echo ("<td>{$item->name}</td>\n");
					echo("<td>{$item->detailedForecast}</td>\n");
					echo("</tr>\n");
				}
				?>
				</table>
             </div>
        </div>
        <!-- /.row -->

        <hr>


        <!-- Footer -->
        <footer>
            <div class="row">
                <div class="col-md-12">
                    <p>Data source: <IMG SRC="../images/weathergovicon.jpg"> at <?php echo (gmdate("Y-m-d h:i:s A",$data->Response->Cache));?> UTC</p>
                    <p>Copyright &copy; 2019 Pathfinder Associates, Inc.</p>
                </div>
            </div>
            <!-- /.row -->
        </footer>

    </div>
    <!-- /.container -->

</body>

</html>
