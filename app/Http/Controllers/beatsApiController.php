<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
// use Illuminate\Support\Facades\DB;
use App\Models\create_beats_table;
use App\Models\tags;
use App\Models\login_signup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Mail\Register;
use App\Mail\FreeBeats;
use App\Mail\Mailme;
use App\Mail\NewPassword;
use Illuminate\Support\Facades\Mail;
use App\Models\LoginSession;
use App\Models\paiements;
use App\Models\favoris;
use App\Models\comment;
use App\Models\License;
use File;
use Illuminate\Support\Facades\Storage;
// // use App\Classes\PayPalPayment;
// // require_once "PayPalPayment.php";
// include(app_path() . '/Providers/PayPalPayment.php');
class PayPalPayment
{

    protected $sandbox_mode,
        $client_id,
        $client_secret,
        $access_token;


    public function __construct()
    {
        $this->sandbox_mode = 1;
        $this->client_id = "";
        $this->client_secret = "";
        $this->access_token = "";
    }

    /**
     * Définit le mode Sandbox / Live du paiement : 1 (ou true) pour le mode Sandbox, 0 (ou false) pour le mode Live
     */
    public function setSandboxMode($mode)
    {
        $this->sandbox_mode = ($mode) ? true : false;
    }

    /**
     * Définit le Client ID à utiliser (à récupérer dans les Credentials PayPal)
     */
    public function setClientID($clientid)
    {
        $this->client_id = $clientid;
    }

    /**
     * Définit le Secret à utiliser (à récupérer dans les Credentials PayPal)
     */
    public function setSecret($secret)
    {
        $this->client_secret = $secret;
    }

    /**
     * Génère un access token depuis l'API PayPal et le stock en variable de session
     * Renvoie l'access token généré si réussi sinon false
     * (Pour communiquer avec l'API PayPal, il est obligatoire de s'authentifier à l'aide de ce "Access Token" qui est généré à partir des Credentials : Client ID et Secret)
     */
    public function generateAccessToken()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->sandbox_mode) {
            curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/oauth2/token");  //DUMMY
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/oauth2/token");  //LIVE
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERPWD, $this->client_id . ":" . $this->client_secret);

        $headers = array();
        $headers[] = "Accept: application/json";
        $headers[] = "Accept-Language: en_US";
        $headers[] = "Content-Type: application/x-www-form-urlencoded";
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $result = curl_exec($ch);
        $data = json_decode($result);
        curl_close($ch);

        $access_token = $data->access_token;

        // Récupérer le nombre de secondes avant expiration :
        $timestamp_expiration = intval($data->expires_in) - 120; // Timestamp donné -2 minutes (marge supplémentaire)

        // Création des variables de session avec expiration_date et access_token
        $_SESSION['paypal_token'] = [];
        $_SESSION['paypal_token']['access_token'] = $access_token;
        $_SESSION['paypal_token']['expiration_timestamp'] = time() + $timestamp_expiration;


        if ($access_token) {
            return $access_token;
        } else {
            return false;
        }
    }

    /**
     * Renvoie un access token (demande à en générer un nouveau si besoin)
     */
    public function getAccessToken()
    {
        if ($this->access_token) {
            return $this->access_token;
        } else {
            $access_token = "";
            if (!empty($_SESSION['paypal_token'])) {
                // Vérifier si le token n'a pas expiré
                if (time() <= $_SESSION['paypal_token']['expiration_timestamp']) {
                    if (!empty($_SESSION['paypal_token']['access_token'])) {
                        $access_token = $_SESSION['paypal_token']['access_token'];
                    }
                }
            }

            // Si l'access_token renvoyé est vide, on en génère un nouveau
            if (!$access_token) {
                $access_token = $this->generateAccessToken();
            }

            return $access_token;
        }
    }

    /**
     * Crée le paiement via l'API PayPal et renvoie la réponse du serveur PayPal
     */
    public function createPayment($payment_data)
    {
        /* Exemple de format pour le paramètre $payment_data à passer :
		$payment_data = [
			"intent" => "sale",
			"redirect_urls" => [
				"return_url" => "http://localhost/",
				"cancel_url" => "http://localhost/"
			],
			"payer" => [
				"payment_method" => "paypal"
			],
			"transactions" => [
				[
					"amount" => [
						"total" => "Montant total de la transaction",
						"currency" => "EUR" // USD, CAD, etc.
					],
					"item_list" => [
						"items" => [
							[
								"quantity" => "1",
								"sku" => "Code de l'item"
								"name" => "Nom de l'item",
								"price" => "xx.xx",
								"currency" => "EUR"
							]
						]
					],
					"description" => "Description du paiement..."
				]
			]
		];
		*/

        $authorization = "Authorization: Bearer " . $this->getAccessToken();

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        if ($this->sandbox_mode) {
            curl_setopt($ch, CURLOPT_URL, "https://api.sandbox.paypal.com/v1/payments/payment");
        } else {
            curl_setopt($ch, CURLOPT_URL, "https://api.paypal.com/v1/payments/payment");
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_data));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);

        return $server_output;
    }

    /**
     * Exécute un paiement via l'API PayPal et renvoie la réponse de PayPal
     */
    public function executePayment($paymentID, $payerID)
    {
        if ($this->sandbox_mode) {
            $paypal_url = "https://api.sandbox.paypal.com/v1/payments/payment/" . $paymentID . "/execute/";
        } else {
            $paypal_url = "https://api.paypal.com/v1/payments/payment/" . $paymentID . "/execute/";
        }
        $authorization = "Authorization: Bearer " . $this->getAccessToken();

        $data = ["payer_id" => $payerID];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_URL, $paypal_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $authorization));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($ch);
        curl_close($ch);

        return $server_output;
    }
}
class beatsApiController extends Controller
{
    public function paypal_create_payment()
    {
        $success = 0;
        $msg = "Une erreur est survenue, merci de bien vouloir réessayer ultérieurement...";
        $paypal_response = [];
        $paypal = new PayPalPayment();
        $paypal->setSandboxMode(1); // On active le mode Sandbox
        $paypal->setClientID("AWZ7Vo7HEwlBb0nlpRP3sKBpWarq6_cfAkffunHnBH519C-owYDOFFAfLB5J7FWsqCK2RFmGQt2-Tsdr"); // On indique sont Client ID
        $paypal->setSecret("EFO1sIJ-NVUvWzgScDBgDvkcwrAasUQJ2HOrhD4bPi41YqYaKNwIjLK5MWSJfMyC9uPdht6kv8sfzuwF"); // On indique son Secret

        $payment_data = [
            "intent" => "sale",
            "redirect_urls" => [
                "return_url" => "http://localhost:8080/",
                "cancel_url" => "http://localhost:8080/"
            ],
            "payer" => [
                "payment_method" => "paypal"
            ],
            "transactions" => [
                [
                    "amount" => [
                        "total" => "9.99", // Prix total de la transaction, ici le prix de notre item
                        "currency" => "EUR" // USD, CAD, etc.
                    ],
                    "item_list" => [
                        "items" => [
                            [
                                "sku" => "1PK5Z9", // Un identifiant quelconque (code / référence) que vous pouvez attribuer au produit que vous vendez
                                "quantity" => "1",
                                "name" => "Un produit quelconque",
                                "price" => "9.99",
                                "currency" => "EUR"
                            ]
                        ]
                    ],
                    "description" => "Description du paiement..."
                ]
            ]
        ];
        $paypal_response = $paypal->createPayment($payment_data);
        $paypal_response = json_decode($paypal_response);
        if (!empty($paypal_response->id)) {
            $paiements = new paiements();
            $paiements->payment_id = $paypal_response->id;
            $paiements->payment_status = $paypal_response->state;
            $paiements->payment_amount = $paypal_response->transactions[0]->amount->total;
            $paiements->payment_currency = $paypal_response->transactions[0]->amount->currency;
            $paiements->payer_email = "jbull635@gmail.com";
            $paiements->save();
            $success = 1;
            $msg = "";
        } else {
            $msg = "Une erreur est survenue durant la communication avec les serveurs de PayPal. Merci de bien vouloir réessayer ultérieurement. 1";
        }
        return json_encode(["success" => $success, "msg" => $msg, "paypal_response" => $paypal_response]);
    }
    public function paypal_execute_payment(Request $request)
    {
        $success = 0;
        $msg = "Une erreur est survenue, merci de bien vouloir réessayer ultérieurement...";
        $paypal_response = [];

        if (!empty($request->paymentID) and !empty($request->payerID)) {
            $paymentID = htmlspecialchars($request->paymentID);
            $payerID = htmlspecialchars($request->payerID);

            $payer = new PayPalPayment();
            $payer->setSandboxMode(1); // On active le mode Sandbox
            $payer->setClientID("AWZ7Vo7HEwlBb0nlpRP3sKBpWarq6_cfAkffunHnBH519C-owYDOFFAfLB5J7FWsqCK2RFmGQt2-Tsdr"); // On indique sont Client ID
            $payer->setSecret("EFO1sIJ-NVUvWzgScDBgDvkcwrAasUQJ2HOrhD4bPi41YqYaKNwIjLK5MWSJfMyC9uPdht6kv8sfzuwF"); // On indique son Secret

            // $payment = $bdd->prepare('SELECT * FROM paiements WHERE payment_id = ?');
            // $payment->execute(array($paymentID));
            // $payment = $payment->fetch();

            $payment = paiements::where('payment_id', "=", $paymentID)->count();

            if ($payment > 0) {
                $paypal_response = $payer->executePayment($paymentID, $payerID);
                $paypal_response = json_decode($paypal_response);

                // $update_payment = $bdd->prepare('UPDATE paiements SET payment_status = ?, payer_email = ? WHERE payment_id = ?');
                // $update_payment->execute(array($paypal_response->state, $paypal_response->payer->payer_info->email, $paymentID));

                paiements::where('payment_id', "=",  $paymentID)->update(['payment_status' => $paypal_response->state, "payer_email" => $paypal_response->payer->payer_info->email]);

                if ($paypal_response->state == "approved") {
                    $success = 1;
                    $msg = "";
                } else {
                    $msg = "Une erreur est survenue durant l'approbation de votre paiement. Merci de réessayer ultérieurement ou contacter un administrateur du site. 2";
                }
            } else {
                $msg = "Votre paiement n'a pas été trouvé dans notre base de données. Merci de réessayer ultérieurement ou contacter un administrateur du site. (Votre compte PayPal n'a pas été débité) 2";
            }
        }
        echo json_encode(["success" => $success, "msg" => $msg, "paypal_response" => $paypal_response]);
    }
    public function register(Request $request)
    {
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $verify_if_email_exist =  login_signup::where('email', "=", $request->email)->count();
            if ($verify_if_email_exist > 0) {
                return 'This email already exists.';
            } else if ($verify_if_email_exist == 0) {
                //$hashed_random_password = Hash::make(Str::random(8));  //Hash::make(Str::random(8));
                $pass_without_hash = Str::random(8);
                $accounts = new  login_signup;
                $accounts->email =  $request->email;
                $accounts->password = Hash::make($pass_without_hash);
                $accounts->save();
                $user_password = ["new_user_generated_password" => $pass_without_hash, "user_mail" => $request->email];
                Mail::to($request->email)->send(new Register($user_password));
                return 'successfully connected.';
            }
        } else {
            return 'Enter a valid email.';
        }
    }
    public function login(Request $request)
    {
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $verify_if_email_exist_and_pass_correct =  login_signup::where('email', "=", $request->email)->count();
            if ($verify_if_email_exist_and_pass_correct > 0) {
                $hashedPassword =  login_signup::where('email', "=", $request->email)->value("password");
                if (Hash::check($request->password, $hashedPassword)) {
                    $token_simple = Str::random(60);
                    $session_token = Hash::make($token_simple);
                    $LoginSession_verify =  LoginSession::where('email', "=", $request->email)->count();
                    if ($LoginSession_verify > 0) {
                        LoginSession::where('email', '=', $request->email)->delete();
                        $LoginSession = new LoginSession();
                        $LoginSession->email = $request->email;
                        $LoginSession->token = $session_token;
                        $LoginSession->save();
                        return  $session_token;
                    } else {
                        $LoginSession = new LoginSession();
                        $LoginSession->email = $request->email;
                        $LoginSession->token = $session_token;
                        $LoginSession->save();
                        return  $session_token;
                    }
                } else {
                    return 'Cannot login, check your password or email.';
                }
            } else {
                return 'Cannot login, check your password or email.';
            }
        } else {
            return 'Enter a valid email.';
        }
    }
    public function new_password(Request $request)
    {
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $verify_if_email_exist =  login_signup::where('email', "=", $request->email)->count();
            if ($verify_if_email_exist > 0) {
                $pass_without_hash = Str::random(8);
                login_signup::where('email', "=", $request->email)->update(['password' => Hash::make($pass_without_hash)]);
                $user_password = ["new_password" => $pass_without_hash, "email" => $request->email];
                Mail::to($request->email)->send(new NewPassword($user_password));
                return "new password sent";
            } else {
                return 'No account matches with this email.';
            }
        } else {
            return 'Enter a valid email.';
        }
    }
    public function check_session_token(Request $request)
    {
        $verify_token_correct =  LoginSession::where('token', "=", $request->token)->count();
        if ($verify_token_correct > 0) {
            return 'Already connected';
        }
    }
    public function check_session_token_2($request)
    {
        $verify_token_correct =  LoginSession::where('token', "=", $request)->count();
        if ($verify_token_correct > 0) {
            return 'Already connected';
        }
    }
    public function favoris(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            $verify_if_favoris_exist =  favoris::where('foreign_id', "=", $request->foreign_id)->where('email', "=",  LoginSession::where('token', "=", $request->token)->value('email'))->count();
            if ($verify_if_favoris_exist == 0) {
                $favoris = new favoris();
                $favoris->email =  LoginSession::where('token', "=", $request->token)->value('email');
                $favoris->foreign_id =  $request->foreign_id;
                $favoris->save();
                $user_email = LoginSession::where('token', "=", $request->token)->value('email');
                $count_current_user_favoris =  favoris::where('email', "=", $user_email)->count();
                $response =  (object)[];
                $response->message =  'Beat successfully added !';
                $response->user_favoris_count =  $count_current_user_favoris;
                return json_encode($response);
            } else {
                return "Beat already in wishlist";
            }
        } else {
            return "Not connected";
        }
    }
    public function get_favoris(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            $get_mail = LoginSession::where('token', "=", $request->token)->value('email');
            $count_current_user_favoris =  favoris::where('email', "=", $get_mail)->count();
            return $count_current_user_favoris;
        } else {
            return 'Not connected';
        }
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return create_beats_table::orderBy('id', "DESC")->skip(0)->take(1)->get();
    }
    public function all_beats()
    {
        return create_beats_table::orderBy('id', "ASC")->get();
    }
    public function beat_desc($id)
    {
        // return create_beats_table::where('id', "=", $id)->get();
        return create_beats_table::select(create_beats_table::raw('id,tags,genre,duration,bpm,beat_link,price,title,downloadable,instagram_name,image_link,src,created_at,DATE_FORMAT(created_at, "%D %b %Y") as correct_date'))->where('id', "=", $id)->get();
    }

    public function tags($id)
    {
        return tags::where('foreign_id', '=', $id)->skip(0)->take(3)->get();
    }
    public function Alltags()
    {
        return tags::all();
    }
    public function moods()
    {
        return create_beats_table::select('mood')->distinct()->get();
    }
    public function genre()
    {
        return create_beats_table::select('genre')->distinct()->get();
    }
    public function keys()
    {
        return create_beats_table::select('key')->distinct()->get();
    }

    public function select_depending_on_genre($genre)
    {
        return create_beats_table::where("genre", "=", $genre)->orderBy('id', "DESC")->get();
    }
    public function search_engine($engine_object)
    { //return var_dump (json_decode($engine_object, true));
        $engine_object_to_array =  json_decode($engine_object, true);
        $final_query = [];
        $array_auto = [];
        $etat1  = null;
        $etat2 = null;
        $etat3 = null;
        foreach (array_keys($engine_object_to_array) as $key => $value) {
            if ($engine_object_to_array["q"] !== 'all') {
                if ($value == "q") {
                    array_push($array_auto, "tags.tags LIKE " . "'" . "%" . str_replace("+", " ", $engine_object_to_array[$value]) . "%" . "'");
                }
                if ($value != "q" and $value != "price" and $value != "bpm") {
                    array_push($array_auto, "`" . $value . "`" . " = " . "'" . str_replace("+", " ", $engine_object_to_array[$value])  . "'");
                }
                if ($value == "price" || $value == "bpm") {
                    $explode_value = explode("-", $engine_object_to_array[$value]);
                    array_push($array_auto, $value . " BETWEEN " . $explode_value[0] . " AND " . $explode_value[1]);
                }
                //
                $etat1 = 'ok';
            } else  if ($engine_object_to_array["q"] == 'all') {

                if (count(array_keys($engine_object_to_array)) > 1) {
                    if ($value != "q" and $value != "price" and $value != "bpm") {
                        array_push($array_auto, "`" . $value . "`" . " = " . "'" . str_replace("+", " ", $engine_object_to_array[$value])  . "'");
                    }
                    if ($value == "price" || $value == "bpm") {
                        $explode_value = explode("-", $engine_object_to_array[$value]);
                        array_push($array_auto, $value . " BETWEEN " . $explode_value[0] . " AND " . $explode_value[1]);
                    }

                    $etat2 = 'ok';
                } else {
                    $etat3 = 'ok';
                }
            }
        }
        if ($etat1 == 'ok') {
            return create_beats_table::join('tags', "create_beats_tables.id", "=", "tags.foreign_id")->whereRaw(implode(" AND ", $array_auto))->select('create_beats_tables.*')->distinct()->get();
        }
        if ($etat2 == 'ok') {
            return  create_beats_table::whereRaw(implode(" AND ", $array_auto))->get();
        }
        if ($etat3 == 'ok') {
            return create_beats_table::all();
        }
    }
    public function favoris_show(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            $get_mail = LoginSession::where('token', "=", $request->token)->value('email');
            return create_beats_table::join('favoris', "create_beats_tables.id", "=", "favoris.foreign_id")->where("favoris.email", "=", $get_mail)->select('create_beats_tables.*')->get();
        } else {
            return 'Not connected';
        }
    }
    public function favoris_delete(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            $get_mail = LoginSession::where('token', "=", $request->token)->value('email');
            favoris::where('email', '=',  $get_mail)->where('foreign_id', '=',  $request->foreign_id)->delete();
            return create_beats_table::join('favoris', "create_beats_tables.id", "=", "favoris.foreign_id")->where("favoris.email", "=", $get_mail)->select('create_beats_tables.*')->get();
        } else {
            return 'Not connected';
        }
    }
    public function update_password(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            if (preg_match("/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*#?&])[A-Za-z\d@$!%*#?&]{8,}$/", $request->password)) {
                $get_mail = LoginSession::where('token', "=", $request->token)->value('email');
                login_signup::where('email', "=",  $get_mail)->update(['password' => Hash::make($request->password)]);
                return 'Password has been changed successfully.';
            } else {
                return 'Please enter minimum eight characters, at least one letter, one number and one special character.';
            }
        } else {
            return 'Not connected.';
        }
    }
    public function get_desc_siblings(Request $request)
    {
        return create_beats_table::where('genre', "=", $request->genre)->get();
    }
    public function add_comment(Request $request)
    {
        if ($this->check_session_token_2($request->token) == "Already connected") {
            $get_mail = LoginSession::where('token', "=", $request->token)->value('email');
            $comment = new  comment;
            $comment->foreign_id = $request->foreign_id;
            $comment->user_email = $get_mail;
            $comment->comment_text = $request->comment;
            $comment->save();
            return 'Comment successfully added';
        } else {
            return 'Not connected.';
        }
    }
    public function show_comment(Request $request)
    {
        return comment::select(comment::raw('SUBSTRING(user_email, 5, 20) as user_email, comment_text,created_at,DATE_FORMAT(created_at, "%D %b %Y") as date_correct'))->where('foreign_id', "=", $request->foreign_id)->get();
    }

    /**
     * FREE DONWLOAD PART
     */
    public function free_download_send_mail(Request $request)
    {
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            // echo $_SERVER['SERVER_NAME'];
            //return $_SERVER;
            if (create_beats_table::where('id', "=", $request->id)->value("downloadable") == 'true') {
                $beat_image =  create_beats_table::where('id', "=", $request->id)->value("image_link");
                $beat_name =  create_beats_table::where('id', "=", $request->id)->value("title");
                $pieces = explode("audio/", create_beats_table::where('id', "=", $request->id)->value("src"));
                $beat_donwload =  "https://49keysbanger.com/" . "server-side/storage/app/public/audio/" . $pieces[1];
                $free_download_info = ["beat_image" => $beat_image, "beat_name" => $beat_name, "beat_donwload" => $beat_donwload];
                Mail::to($request->email)->send(new FreeBeats($free_download_info));
                return 'The instrument has been sent to your email address.';
            } else {
                return "you are not authorized to access this link.";
            }
        } else {
            return "Please enter a valid email";
        }
    }

    /*
    Private message
    */
    public function email_me(Request $request)
    {
        if (filter_var($request->email, FILTER_VALIDATE_EMAIL)) {
            $mail_info = ["mailer_name" => $request->name, "mailer_mail" => $request->email, "mailer_subject" => $request->subject, "mailer_message" => $request->message];
            Mail::to("thetrackmonster@gmail.com")->send(new Mailme($mail_info));
        } else {
            return "Please enter a valid email";
        }
    }

    // public function free_download(Request $request)
    // {

    //     if (create_beats_table::where('id', "=", $request->id)->value("downloadable") == 'true') {
    //         $pieces = explode("audio/", create_beats_table::where('id', "=", $request->id)->value("src"));
    //         return Storage::download('public/audio/' . $pieces[1]);
    //     } else {
    //         return "you are not authorized to access this link.";
    //     }
    // }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //return $id;
        return create_beats_table::where("id", "=", $id)->get();
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
