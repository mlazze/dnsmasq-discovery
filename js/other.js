function wol(braddress, mac) {
    alert("WoL magic packet sento to " + mac);
      var xhttp = new XMLHttpRequest();
          xhttp.open("GET", "wol.php?MAC="+mac+"&braddress="+braddress, true);
          xhttp.send();
}

function update() {
      var e = document.getElementsByClassName("update");
          e[0].style.color="#800";
          e[0].innerHTML="Updating...";
          e[1].style.color="#800";
          e[1].innerHTML="Updating...";
      var xhttp = new XMLHttpRequest();
          xhttp.addEventListener("load", transferComplete, false);
          xhttp.addEventListener("error", transferFailed, false);
          xhttp.open("GET", "getHosts.php", true);
          xhttp.send();

    function transferComplete() {
        location.reload();
    }

    function transferFailed() {
        alert("Error while updating");
    }
}


