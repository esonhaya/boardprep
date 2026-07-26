function updateDifficulty(changed)
{

    let values = {

        easy:
            Number(
                document.getElementById("easy").value
            ),

        medium:
            Number(
                document.getElementById("medium").value
            ),

        hard:
            Number(
                document.getElementById("hard").value
            )

    };


    let total =
        values.easy +
        values.medium +
        values.hard;


    if(total > 100)
    {

        let excess =
            total - 100;


        let others =
        [
            "easy",
            "medium",
            "hard"
        ]
        .filter(
            item => item !== changed
        );


        for(
            let item of others
        )
        {

            let reduce =
                Math.min(
                    values[item],
                    excess
                );


            values[item] -= reduce;

            excess -= reduce;


            if(excess <= 0)
            {
                break;
            }

        }

    }


    for(
        let key in values
    )
    {

        document.getElementById(key).value =
            values[key];


        document.getElementById(key + "Input").value =
            values[key];

    }


    document.getElementById("easyValue").innerHTML =
        values.easy;


    document.getElementById("mediumValue").innerHTML =
        values.medium;


    document.getElementById("hardValue").innerHTML =
        values.hard;


    let finalTotal =
        values.easy +
        values.medium +
        values.hard;


    document.getElementById("total").innerHTML =
        finalTotal;


    document.getElementById("status").innerHTML =
        finalTotal === 100
        ? "✅"
        : "❌";


    document.getElementById("submitButton").disabled =
        finalTotal !== 100;

}



[
    "easy",
    "medium",
    "hard"

]
.forEach(
function(id)
{

    document
    .getElementById(id)
    .addEventListener(
        "input",
        function()
        {

            updateDifficulty(id);

        }
    );

});



[
    "easyInput",
    "mediumInput",
    "hardInput"

]
.forEach(
function(id)
{

    document
    .getElementById(id)
    .addEventListener(
        "input",
        function()
        {

            let slider =
                id.replace(
                    "Input",
                    ""
                );


            document
            .getElementById(slider)
            .value =
                this.value;


            updateDifficulty(slider);

        }
    );

});



updateDifficulty("easy");
